<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A per-series document number counter (D-24). Incremented only by
 * RunningNumberService under a row lock.
 *
 * @property int $id
 * @property string $key
 * @property string $prefix
 * @property int $year
 * @property int $last_number
 */
#[Fillable(['key', 'prefix', 'year', 'last_number'])]
class RunningNumber extends Model {}
