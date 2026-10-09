<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Enum;

enum DatePickerRangePreset: string
{
    case ThisWeek = 'thisWeek';
    case LastWeek = 'lastWeek';
    case ThisMonth = 'thisMonth';
    case LastMonth = 'lastMonth';
    case ThisQuarter = 'thisQuarter';
    case LastQuarter = 'lastQuarter';
    case ThisYear = 'thisYear';
    case LastYear = 'lastYear';
    case Last3Days = 'last3Days';
    case Last7Days = 'last7Days';
    case Last14Days = 'last14Days';
    case Last30Days = 'last30Days';
    case Last90Days = 'last90Days';
}
