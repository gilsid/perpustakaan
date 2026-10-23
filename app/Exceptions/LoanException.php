<?php

namespace App\Exceptions;

use RuntimeException;

/** Pelanggaran aturan peminjaman. Pesannya aman ditampilkan ke pengguna. */
class LoanException extends RuntimeException {}
