<?php

use App\Support\Readable;

it('writes bank text the way people write it', function (string $bank, string $readable) {
    expect(Readable::text($bank))->toBe($readable);
})->with([
    ['WOOLWORTHS CAVENDISH', 'Woolworths Cavendish'],
    ['TAKEALOT.COM CAPE TOWN ZA', 'Takealot.com Cape Town ZA'],
    ["COL'CACCHIO CLAREMONT", "Col'cacchio Claremont"],
    ['IB TRANSFER FROM 9930', 'IB Transfer from 9930'],     // two letters are an abbreviation, "from" stays small
    ['PPS LIFE COVER', 'PPS Life Cover'],                     // no vowels: an abbreviation
    ['CHECKERS SIXTY60', 'Checkers Sixty60'],
    ['DSTV', 'DSTV'],
    ['MR D FOOD', 'Mr D Food'],
    ['TO SAVINGS', 'To Savings'],                             // a small word first is still a capital
]);

it('leaves text with its own capitals as it is', function () {
    expect(Readable::text('Cash from Aunt Mary'))->toBe('Cash from Aunt Mary')
        ->and(Readable::text(''))->toBe('')
        ->and(Readable::text(null))->toBe('');
});
