<?php

namespace App\Exceptions;

use RuntimeException;

/** A rejected upload. The message is written for the person uploading. */
class InvalidScormPackage extends RuntimeException {}
