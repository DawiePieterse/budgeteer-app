<?php

namespace App\Http\Controllers;

use App\Models\StatementImport;
use App\Statements\StatementDoesNotAddUp;
use App\Statements\StatementReaders;
use App\Statements\StatementText;
use App\Statements\UnreadableStatement;
use App\Transactions\StatementImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

class StatementController extends Controller
{
    private const SESSION_KEY = 'statement_upload';

    public function index(): View
    {
        return view('statements.index', [
            'imports' => StatementImport::query()->with(['account', 'user'])->latest()->limit(50)->get(),
        ]);
    }

    /** The browser has read the PDF and sends its text; show what was found before saving anything. */
    public function preview(Request $request, StatementReaders $readers, StatementImporter $importer): View|RedirectResponse
    {
        $request->validate(['text' => ['required', 'string', 'max:5000000']]);

        try {
            $json = json_decode($request->string('text'), true, 16, JSON_THROW_ON_ERROR);
            $statement = $readers->read(StatementText::fromArray($json));
        } catch (JsonException|InvalidArgumentException) {
            return back()->with('error', 'The file could not be read as a statement.');
        } catch (UnreadableStatement|StatementDoesNotAddUp $e) {
            return back()->with('error', $e->getMessage());
        }

        $household = $request->user()->household;
        $request->session()->put(self::SESSION_KEY, $request->string('text')->toString());

        return view('statements.preview', [
            'statement' => $statement,
            'account' => $importer->existingAccount($statement, $household),
            'alreadyImported' => $importer->alreadyImported($statement, $household),
            'alreadyThere' => $importer->countAlreadyThere($statement, $household),
            'tooOld' => $household->keep_from === null ? 0 : count(array_filter($statement->lines, fn ($l) => $l->date->lessThan($household->keep_from))),
            'keepFrom' => $household->keep_from,
        ]);
    }

    public function store(Request $request, StatementReaders $readers, StatementImporter $importer): RedirectResponse
    {
        $text = $request->session()->pull(self::SESSION_KEY);
        if (! is_string($text)) {
            return redirect()->route('statements.index')->with('error', 'Please choose the statement again.');
        }

        try {
            $statement = $readers->read(StatementText::fromArray(json_decode($text, true)));
            $import = $importer->import($statement, $request->user());
        } catch (UnreadableStatement|StatementDoesNotAddUp|RuntimeException $e) {
            return redirect()->route('statements.index')->with('error', $e->getMessage());
        }

        $notes = array_filter([
            $import->matched_emails > 0 ? "{$import->matched_emails} already read from bank emails" : null,
            $import->already_there > 0 ? "{$import->already_there} already there from an earlier statement" : null,
        ]);

        return redirect()->route('categorise')->with('status', "Imported {$import->added} transactions".($notes ? ' ('.implode('; ', $notes).').' : '.'));
    }
}
