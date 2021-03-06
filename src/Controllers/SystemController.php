<?php

namespace Exceedone\Exment\Controllers;

use Encore\Admin\Layout\Content;
use Encore\Admin\Widgets\Box;
use Encore\Admin\Widgets\Form as WidgetForm;
use Exceedone\Exment\Enums;
use Exceedone\Exment\Enums\CustomValueAutoShare;
use Exceedone\Exment\Enums\FilterSearchType;
use Exceedone\Exment\Enums\JoinedOrgFilterType;
use Exceedone\Exment\Enums\JoinedMultiUserFilterType;
use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Enums\SystemVersion;
use Exceedone\Exment\Enums\SystemColumn;
use Exceedone\Exment\Exment;
use Exceedone\Exment\Form\Tools;
use Exceedone\Exment\Form\Widgets\InfoBox;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\Define;
use Exceedone\Exment\Model\System;
use Exceedone\Exment\Services\Installer\InitializeFormTrait;
use Exceedone\Exment\Services\NotifyService;
use Exceedone\Exment\Services\SystemRequire\SystemRequireList;
use Exceedone\Exment\Enums\SystemRequireCalledType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Validator;

class SystemController extends AdminControllerBase
{
    use InitializeFormTrait;
    
    public function __construct()
    {
        $this->setPageInfo(exmtrans("system.header"), exmtrans("system.system_header"), exmtrans("system.system_description"), 'fa-cogs');
    }

    /**
     * Index interface.
     *
     * @return Content
     */
    public function index(Request $request, Content $content)
    {
        if ($request->has('advanced')) {
            return $this->formAdvancedBox($request, $content);
        }

        return $this->formBasicBox($request, $content);
    }

    /**
     * Index interface.
     *
     * @return Content
     */
    protected function formBasicBox(Request $request, Content $content)
    {
        $this->AdminContent($content);
        $form = $this->formBasic($request);

        $box = new Box(exmtrans('common.basic_setting'), $form);
        $box->tools(new Tools\SystemChangePageMenu());

        $content->row($box);

        if (System::outside_api()) {
            // Version infomation
            $infoBox = $this->getVersionBox();
            $content->row(new Box(exmtrans("system.version_header"), $infoBox->render()));
        }

        // Append system require box
        $box = $this->getSystemRequireBox();
        $content->row(new Box(exmtrans("install.system_require.header"), $box->render()));

        return $content;
    }

    /**
     * Index interface.
     *
     * @return Content
     */
    protected function formBasic(Request $request) : WidgetForm
    {
        $form = $this->getInitializeForm('system', false);
        $form->action(admin_url('system'));
  
        $admin_users = System::system_admin_users();
        $form->multipleSelect('system_admin_users', exmtrans('system.system_admin_users'))
            ->help(exmtrans('system.help.system_admin_users'))
            ->required()
            ->ajax(CustomTable::getEloquent(SystemTableName::USER)->getOptionAjaxUrl())
            ->options(function ($option) use ($admin_users) {
                return CustomTable::getEloquent(SystemTableName::USER)->getSelectOptions([
                    'selected_value' => $admin_users,
                ]);
            })->default($admin_users);

        return $form;
    }

    /**
     * index advanced setting
     *
     * @param Request $request
     * @param Content $content
     * @return Content
     */
    protected function formAdvancedBox(Request $request, Content $content)
    {
        $this->AdminContent($content);

        $form = $this->formAdvanced($request);

        $box = new Box(exmtrans('common.detail_setting'), $form);

        $box->tools(new Tools\SystemChangePageMenu());

        $content->row($box);

        // sendmail test
        $content->row($this->getsendmailTestBox());

        return $content;
    }


    /**
     * index advanced setting
     *
     * @param Request $request
     * @param Content $content
     * @return Content
     */
    protected function formAdvanced(Request $request) : WidgetForm
    {
        $form = new WidgetForm(System::get_system_values(['advanced', 'notify']));
        $form->disableReset();
        $form->action(admin_url('system'));

        $form->progressTracker()->options($this->getProgressInfo(true));
        
        $form->hidden('advanced')->default(1);
        $form->ignore('advanced');

        $form->select('grid_pager_count', exmtrans("system.grid_pager_count"))
        ->options(getPagerOptions())
        ->config('allowClear', false)
        ->default(20)
        ->help(exmtrans("system.help.grid_pager_count"));
            
        $form->select('datalist_pager_count', exmtrans("system.datalist_pager_count"))
            ->options(getPagerOptions(false, Define::PAGER_DATALIST_COUNTS))
            ->config('allowClear', false)
            ->default(5)
            ->help(exmtrans("system.help.datalist_pager_count"));
        
        $form->select('default_date_format', exmtrans("system.default_date_format"))
            ->options(getTransArray(Define::SYSTEM_DATE_FORMAT, "system.date_format_options"))
            ->config('allowClear', false)
            ->default('format_default')
            ->help(exmtrans("system.help.default_date_format"));

        $form->select('filter_search_type', exmtrans("system.filter_search_type"))
            ->default(FilterSearchType::FORWARD)
            ->options(FilterSearchType::transArray("system.filter_search_type_options"))
            ->config('allowClear', false)
            ->required()
            ->help(exmtrans("system.help.filter_search_type"));

        $form->checkbox('grid_filter_disable_flg', exmtrans("system.grid_filter_disable_flg"))
            ->options(function () {
                return collect(SystemColumn::transArray("common"))->filter(function ($value, $key) {
                    return boolval(array_get(SystemColumn::getOption(['name' => $key]), 'grid_filter', false));
                })->toArray();
            })
            ->help(exmtrans("system.help.grid_filter_disable_flg"));

        $form->select('data_submit_redirect', exmtrans("system.data_submit_redirect"))
            ->options(Enums\DataSubmitRedirect::transKeyArray("admin", false))
            ->help(exmtrans("system.help.data_submit_redirect"));

        if (boolval(System::organization_available())) {
            $form->exmheader(exmtrans('system.organization_header'))->hr();

            $manualUrl = getManualUrl('organization');
            $form->select('org_joined_type_role_group', exmtrans("system.org_joined_type_role_group"))
                ->help(exmtrans("system.help.org_joined_type_role_group") . exmtrans("common.help.more_help_here", $manualUrl))
                ->options(JoinedOrgFilterType::transKeyArray('system.joined_org_filter_role_group_options'))
                ->config('allowClear', false)
                ->default(JoinedOrgFilterType::ALL)
                ;

            $form->select('org_joined_type_custom_value', exmtrans("system.org_joined_type_custom_value"))
                ->help(exmtrans("system.help.org_joined_type_custom_value") . exmtrans("common.help.more_help_here", $manualUrl))
                ->options(JoinedOrgFilterType::transKeyArray('system.joined_org_filter_custom_value_options'))
                ->config('allowClear', false)
                ->default(JoinedOrgFilterType::ONLY_JOIN)
                ;

            $form->select('custom_value_save_autoshare', exmtrans("system.custom_value_save_autoshare"))
                ->help(exmtrans("system.help.custom_value_save_autoshare") . exmtrans("common.help.more_help_here", $manualUrl))
                ->options(CustomValueAutoShare::transKeyArray('system.custom_value_save_autoshare_options'))
                ->config('allowClear', false)
                ->default(CustomValueAutoShare::USER_ONLY)
                ;
        }

        $manualUrl = getManualUrl('multiuser');
        $form->select('filter_multi_user', exmtrans(boolval(System::organization_available()) ? "system.filter_multi_orguser" : "system.filter_multi_user"))
            ->help(exmtrans("system.help.filter_multi_orguser") . exmtrans("common.help.more_help_here", $manualUrl))
            ->options(JoinedMultiUserFilterType::getOptions())
            ->config('allowClear', false)
            ->default(JoinedMultiUserFilterType::NOT_FILTER)
        ;

        

        // View and dashbaord ----------------------------------------------------
        $form->exmheader(exmtrans('system.view_dashboard_header'))->hr();

        $form->switchbool('userdashboard_available', exmtrans("system.userdashboard_available"))
            ->default(0)
            ->help(exmtrans("system.help.userdashboard_available"));

        $form->switchbool('userview_available', exmtrans("system.userview_available"))
            ->default(0)
            ->help(exmtrans("system.help.userview_available"));


        // use mail setting
        $this->setNotifyForm($form);

        $form->exmheader(exmtrans('system.ip_filter'))->hr();
        $form->descriptionHtml(exmtrans("system.help.ip_filter"));

        $form->textarea('web_ip_filters', exmtrans('system.web_ip_filters'))->rows(3);
        $form->textarea('api_ip_filters', exmtrans('system.api_ip_filters'))->rows(3);

        return $form;
    }


    /**
     * get exment version infoBox.
     *
     * @return Content
     */
    protected function getVersionBox()
    {
        list($latest, $current) = \Exment::getExmentVersion();
        $version = \Exment::checkLatestVersion();
        $showLink = false;

        if ($version == SystemVersion::ERROR) {
            $message = exmtrans("system.version_error");
            $icon = 'warning';
            $bgColor = 'red';
            $current = '---';
        } elseif ($version == SystemVersion::DEV) {
            $message = exmtrans("system.version_develope");
            $icon = 'legal';
            $bgColor = 'olive';
        } elseif ($version == SystemVersion::LATEST) {
            $message = exmtrans("system.version_latest");
            $icon = 'check-square';
            $bgColor = 'blue';
        } else {
            $message = exmtrans("system.version_old") . '(' . $latest . ')';
            $showLink = true;
            $icon = 'arrow-circle-right';
            $bgColor = 'aqua';
        }
        
        // Version infomation
        $infoBox = new InfoBox(
            exmtrans("system.current_version") . $current,
            $icon,
            $bgColor,
            getManualUrl('update'),
            $message
        );
        $class = $infoBox->getAttributes()['class'];
        $infoBox
            ->class(isset($class)? $class . ' box-version': 'box-version')
            ->showLink($showLink)
            ->target('_blank');
        if ($showLink) {
            $infoBox->linkText(exmtrans("system.update_guide"));
        }

        return $infoBox;
    }


    /**
     * get system require box.
     *
     * @return Content
     */
    protected function getSystemRequireBox()
    {
        $checkResult = SystemRequireList::make(SystemRequireCalledType::WEB);
        $view = view('exment::widgets.system-require', [
            'checkResult' => $checkResult,
        ]);
        return $view;
    }


    /**
     * Send data
     * @param Request $request
     */
    public function post(Request $request)
    {
        $advanced = $request->has('advanced');

        // validation
        $form = $advanced ? $this->formAdvanced($request) : $this->formBasic($request);
        if (($response = $form->validateRedirect($request)) instanceof \Illuminate\Http\RedirectResponse) {
            return $response;
        }

        DB::beginTransaction();
        try {
            $result = $this->postInitializeForm($request, ($advanced ? ['advanced', 'notify'] : ['initialize', 'system']), false, !$advanced);
            if ($result instanceof \Illuminate\Http\RedirectResponse) {
                return $result;
            }

            // Set Role
            if (!$advanced) {
                System::system_admin_users($request->get('system_admin_users'));
            }

            DB::commit();

            admin_toastr(trans('admin.save_succeeded'));

            return redirect(admin_url('system') . ($advanced ? '?advanced=1' : ''));
        } catch (\Exception $exception) {
            //TODO:error handling
            DB::rollback();
            throw $exception;
        }
    }

    /**
     * send test mail
     *
     * @return void
     */
    public function sendTestMail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'test_mail_to' => 'required|email',
        ]);
        if ($validator->fails()) {
            return getAjaxResponse([
                'result'  => false,
                'toastr' => $validator->errors()->first(),
                'reload' => false,
            ]);
        }

        \Exment::setTimeLimitLong();
        $test_mail_to = $request->get('test_mail_to');

        try {
            NotifyService::executeTestNotify([
                'type' => 'mail',
                'to' => $test_mail_to,
            ]);

            return getAjaxResponse([
                'result'  => true,
                'toastr' => exmtrans('common.message.sendmail_succeeded'),
                'reload' => false,
            ]);
        }
        // throw mailsend Exception
        catch (\Swift_TransportException $ex) {
            \Log::error($ex);

            return getAjaxResponse([
                'result'  => false,
                'toastr' => exmtrans('error.mailsend_failed'),
                'reload' => false,
            ]);
        }
    }
}
