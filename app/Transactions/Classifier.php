<?php

namespace App\Transactions;

use App\Enums\Bank;
use App\Enums\TransactionKind;

/**
 * Decides what kind of transaction a statement line is, from the bank's transaction type
 * (Standard Bank, in Afrikaans or English) or the description (Discovery).
 */
class Classifier
{
    /** Standard Bank transaction types, Afrikaans and English, by the start of the type. */
    private const STANDARD_BANK_TYPES = [
        'IB-OORPLASING' => TransactionKind::Transfer,
        'IB OORPLASING' => TransactionKind::Transfer,
        'IB TRANSFER' => TransactionKind::Transfer,
        'IB-BETALING' => TransactionKind::Payment,
        'IB PAYMENT' => TransactionKind::Payment,
        'ONMIDDELLIKE BETALING' => TransactionKind::Payment,
        'IMMEDIATE PAYMENT' => TransactionKind::Payment,
        'KREDIETOORPLASING' => TransactionKind::Deposit,
        'CREDIT TRANSFER' => TransactionKind::Deposit,
        'ELEKTRONIESE BANK BETALING VAN' => TransactionKind::Deposit,
        'ELECTRONIC BANK PAYMENT FROM' => TransactionKind::Deposit,
        'DEBIETOORPLASING' => TransactionKind::DebitOrder,
        'DEBIT TRANSFER' => TransactionKind::DebitOrder,
        'MEDIESEFONSBYDRAE' => TransactionKind::DebitOrder,
        'MEDICAL AID' => TransactionKind::DebitOrder,
        'VERSEKERINGSPREMIE' => TransactionKind::DebitOrder,
        'INSURANCE PREMIUM' => TransactionKind::DebitOrder,
        'DIENSOOREENKOMS' => TransactionKind::DebitOrder,
        'SERVICE AGREEMENT' => TransactionKind::DebitOrder,
        'GEMIGREERDE DC-DEBIET' => TransactionKind::DebitOrder,
        'NAEDO' => TransactionKind::DebitOrder,
        'DEBIT ORDER' => TransactionKind::DebitOrder,
        'OORTREKKINGS RENTE' => TransactionKind::Interest,
        'OVERDRAFT INTEREST' => TransactionKind::Interest,
        'RENTE' => TransactionKind::Interest,
        'INTEREST' => TransactionKind::Interest,
        'FOOI' => TransactionKind::Fee,
        'GELDE' => TransactionKind::Fee,
        'VASTE MAANDELIKSE FOOI' => TransactionKind::Fee,
        'OORTREKKING-DIENSGELD' => TransactionKind::Fee,
        'KONTANTONTTREKKINGSFOOI' => TransactionKind::Fee,
        'FEE' => TransactionKind::Fee,
        'MONTHLY MANAGEMENT FEE' => TransactionKind::Fee,
        'AUTOBANK-KONTANTONTTREKKING' => TransactionKind::Cash,
        'SELFOON KITSONTTR' => TransactionKind::Cash,
        'KONTANTONTTR' => TransactionKind::Cash,
        'AUTOBANK CASH WITHDRAWAL' => TransactionKind::Cash,
        'CASH WITHDRAWAL' => TransactionKind::Cash,
        'LOTERY-AANKOPE' => TransactionKind::Purchase,
        'AANKOPE' => TransactionKind::Purchase,
        'PURCHASE' => TransactionKind::Purchase,
    ];

    /**
     * @param  string  $accountHolder  surname or name as it appears on payments into the account
     */
    public function kind(Bank $bank, ?string $bankType, string $description, int $amountCents, string $accountHolder = ''): TransactionKind
    {
        return match ($bank) {
            Bank::StandardBank => $this->standardBank($bankType, $amountCents),
            Bank::Discovery => $this->discovery($description, $amountCents, $accountHolder),
        };
    }

    private function standardBank(?string $bankType, int $amountCents): TransactionKind
    {
        $type = strtoupper(trim((string) $bankType));
        $best = null;
        foreach (self::STANDARD_BANK_TYPES as $start => $kind) {
            if (str_starts_with($type, $start) && ($best === null || strlen($start) > strlen($best))) {
                $best = $start;
            }
        }

        return $best !== null ? self::STANDARD_BANK_TYPES[$best] : ($amountCents < 0 ? TransactionKind::Payment : TransactionKind::Deposit);
    }

    private function discovery(string $description, int $amountCents, string $accountHolder): TransactionKind
    {
        $d = strtoupper(trim($description));

        return match (true) {
            str_starts_with($d, 'REFUND') => TransactionKind::Refund,
            str_contains($d, 'INTEREST') && $amountCents > 0 => TransactionKind::Interest,
            str_ends_with($d, ' FEE') || str_contains($d, ' FEE ') || str_starts_with($d, 'CARD FEE') || str_starts_with($d, 'INTL PAYMENT FEE') => TransactionKind::Fee,
            $amountCents > 0 && $accountHolder !== '' && $d === strtoupper($accountHolder) => TransactionKind::Transfer,
            $amountCents > 0 => TransactionKind::Deposit,
            preg_match('/\bCARD \.{3,}\d{4}\b/', $d) === 1 => TransactionKind::Cash,
            $d === 'PAY' => TransactionKind::Payment,
            default => TransactionKind::Purchase,
        };
    }
}
