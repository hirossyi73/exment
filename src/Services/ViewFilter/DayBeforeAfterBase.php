<?php
namespace Exceedone\Exment\Services\ViewFilter;

use Exceedone\Exment\Enums\FilterOption;

abstract class DayBeforeAfterBase extends ViewFilterBase
{
    protected function _setFilter($query, $method_name, $query_column, $query_value)
    {
        $isDateTime = $this->column_item->isDateTime();
        $target_day = $this->getTargetDay($query_value);
        $mark = $this->getMark();

        $query->{"{$method_name}DateMarkExment"}($query_column, $target_day, $mark, $isDateTime);
    }
    
    abstract protected function getTargetDay($query_value);

    abstract protected function getMark() : string;
}