<?php

namespace App\Gmail\Parsers;

use RuntimeException;

/** The email is from a known bank but holds no transaction to record, for example a declined purchase. */
class EmailToIgnore extends RuntimeException {}
