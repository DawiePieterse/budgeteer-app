<?php

namespace App\Gmail\Parsers;

use RuntimeException;

/** The email is from a known bank but is not a kind of notification Budgeteer can read yet. */
class EmailNotUnderstood extends RuntimeException {}
