<?php

namespace Exceedone\Exment\Controllers;

use Encore\Admin\Grid;
use Exceedone\Exment\Enums\SummaryCondition;
use Exceedone\Exment\Enums\SystemColumn;
use Exceedone\Exment\Form\Tools;
use Exceedone\Exment\Model\CustomRelation;
use Exceedone\Exment\Model\Plugin;

trait CustomValueSummary
{
    protected function gridSummary()
    {
        $classname = $this->getModelNameDV();
        $grid = new Grid(new $classname);
        Plugin::pluginPreparing($this->plugins, 'loading');

        $this->setSummaryGrid($grid);

        $grid->disableFilter();
        $grid->disableActions();
        $grid->disableRowSelector();
        $grid->disableExport();

        $grid->tools(function (Grid\Tools $tools) use ($grid) {
            //$tools->append(new Tools\ExportImportButton($this->custom_table->table_name, $grid, true));
            $tools->append(new Tools\GridChangePageMenu('data', $this->custom_table, false));
            $tools->append(new Tools\GridChangeView($this->custom_table, $this->custom_view));
        });

        Plugin::pluginPreparing($this->plugins, 'loaded');
        return $grid;
    }

    /**
     * set summary grid
     */
    protected function setSummaryGrid($grid)
    {
        $view = $this->custom_view;

        $query = $grid->model();
        $view->getValueSummary($query, $this->custom_table, $grid);
    }
}
