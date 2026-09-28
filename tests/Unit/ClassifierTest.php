<?php

use App\Enums\Bank;
use App\Enums\TransactionKind;
use App\Transactions\Classifier;
use App\Transactions\MerchantKey;

it('knows Standard Bank transaction types in Afrikaans and English', function (string $type, int $cents, TransactionKind $kind) {
    expect((new Classifier)->kind(Bank::StandardBank, $type, 'X', $cents))->toBe($kind);
})->with([
    ['IB-BETALING NA', -40000, TransactionKind::Payment],
    ['IB-OORPLASING UIT', 20000, TransactionKind::Transfer],
    ['IB OORPLASING NA', -3400, TransactionKind::Transfer],
    ['KREDIETOORPLASING', 6000, TransactionKind::Deposit],
    ['ELEKTRONIESE BANK BETALING VAN', 10000, TransactionKind::Deposit],
    ['MEDIESEFONSBYDRAE', -9383, TransactionKind::DebitOrder],
    ['VERSEKERINGSPREMIE', -1951, TransactionKind::DebitOrder],
    ['NAEDO-GEMIGR. INV. HERVOORL', -104, TransactionKind::DebitOrder],
    ['GEMIGREERDE DC-DEBIET', -104, TransactionKind::DebitOrder],
    ['VASTE MAANDELIKSE FOOI', -49, TransactionKind::Fee],
    ['FOOI ONMIDDELLIKE BETALING', -50, TransactionKind::Fee],
    ['ONMIDDELLIKE BETALING', -20000, TransactionKind::Payment],
    ['OORTREKKINGS RENTE', -2, TransactionKind::Interest],
    ['AUTOBANK-KONTANTONTTREKKING BY', -1000, TransactionKind::Cash],
    ['SOMETHING NEW', -10, TransactionKind::Payment],
    ['SOMETHING NEW', 10, TransactionKind::Deposit],
]);

it('knows Discovery transactions by their description', function (string $description, int $cents, TransactionKind $kind) {
    expect((new Classifier)->kind(Bank::Discovery, null, $description, $cents, 'J SMITH'))->toBe($kind);
})->with([
    ['WOOLWORTHS BELLVILLE', -35610, TransactionKind::Purchase],
    ['Refund PnP Clt Paarl PAARL', 61999, TransactionKind::Refund],
    ['J SMITH', 4000000, TransactionKind::Transfer],
    ['Interest Earned at 0.10%', 633, TransactionKind::Interest],
    ['Dynamic interest boost at 0.75%', 4746, TransactionKind::Interest],
    ['Monthly facility fee', -5500, TransactionKind::Fee],
    ['Card fee ....1234', -5400, TransactionKind::Fee],
    ['Intl payment fee APPLE.COM/BILL', -210, TransactionKind::Fee],
    ['ATM withdrawal fee', -785, TransactionKind::Fee],
    ['Cnr N7 and Main Rd card ...5678 Malmesbury', -10000, TransactionKind::Cash],
    ['Pay', -256350, TransactionKind::Payment],
]);

it('gives one merchant key to branches and changing references', function (string $description, string $key) {
    expect((new MerchantKey)->for($description))->toBe($key);
})->with([
    ['WOOLWORTHS CAPE TOWN', 'WOOLWORTHS'],
    ['WOOLWORTHS BELLVILLE', 'WOOLWORTHS'],
    ['Yoco *Ripple n Tide Velddrif', 'RIPPLE'],
    ['iK *Die posbus VELDDRIF', 'DIE POSBUS'],
    ['PAYFAST*Brabantia Websh', 'BRABANTIA'],
    ['MOMENTUM 12345 FH6789', 'MOMENTUM'],
    ['MOMENTUM 12345 HA9876', 'MOMENTUM'],
    ['AUTOGENVAPPAG1234 MAY 2026', 'AUTOGENVAPPAG'],
    ['AUTOGENVAPPAG1234 JUN 2026', 'AUTOGENVAPPAG'],
    ['A HELPER CLEANING', 'HELPER'],
    ['Nintendo CB1672722610 219.95 ZAR', 'NINTENDO'],
    ['Google One ...0000 250.00 KES', 'GOOGLE'],
    ['12345 67890', 'UNKNOWN'],
]);
