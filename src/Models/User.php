<?php

namespace App\Models;

use App\Controllers\LinkController;
use App\Utils\{
    Tools,
    Hash,
    GA,
    Telegram,
    URL
};
use App\Services\{
    Config, 
    Mail, 
    ZeroConfig
};
use Pkly\I18Next\I18n;
use Ramsey\Uuid\Uuid;
use Exception;

/**
 * User Model
 *
 * @property-read   int $id         ID
 * @property        bool    $is_admin           是否管理员
 * @todo More property
 * @property        bool $expire_notified    If user is notified for expire
 * @property        bool $traffic_notified   If user is noticed for low traffic
 */
class User extends Model
{
    protected $connection = 'default';

    protected $table = 'user';

    /**
     * 已登录
     *
     * @var bool
     */
    public $isLogin;

    /**
     * 强制类型转换
     *
     * @var array
     */
    protected $casts = [
        't'               => 'float',
        'u'               => 'float',
        'd'               => 'float',
        'transfer_enable' => 'float',
        'enable'          => 'int',
        'is_admin'        => 'boolean',
        'node_speedlimit' => 'float',
        'config'          => 'array',
        'ref_by'          => 'int'
    ];

    /**
     * Gravatar 头像地址
     */
    public function getGravatarAttribute()
    {
        // QQ邮箱用户使用QQ头像
        $email_su = substr($this->attributes['email'], -6);
        $email_pre = substr($this->attributes['email'], 0, -7);
        if ($email_su == "qq.com" and is_numeric($email_pre)) {
            return "https://q4.qlogo.cn/g?b=qq&nk=" . $this->attributes['email'] . "&s=3";
        }
        $hash = md5(strtolower(trim($this->attributes['email'])));
        return 'https://gravatar.loli.net/avatar/' . $hash . "?&d=monsterid";
    }

    /**
     * 最后使用时间
     */
    public function lastUseTime(): string
    {
        return $this->t == 0 ? i18n::get()->t('never used') : Tools::toDateTime($this->t);
    }

    /**
     * 生成邀请码
     */
    public function addInviteCode(): string
    {
        while (true) {
            $temp_code = Tools::genRandomChar(10);
            if (InviteCode::where('code', $temp_code)->first() == null) {
                if (InviteCode::where('user_id', $this->id)->count() == 0) {
                    $code          = new InviteCode();
                    $code->code    = $temp_code;
                    $code->user_id = $this->id;
                    $code->save();
                    return $temp_code;
                } else {
                    return (InviteCode::where('user_id', $this->id)->first())->code;
                }
            }
        }
    }
    
    /**
     * 生成新的UUID
     */
    public function generateUUID($s): bool
    {
        $this->uuid = Uuid::uuid5(
            Uuid::NAMESPACE_DNS,
            $this->email . '|' . $s
        );
        return $this->save();
    }

    /*
     * 总流量[自动单位]
     */
    public function enableTraffic(): string
    {
        return Tools::flowAutoShow($this->transfer_enable);
    }

    /*
     * 总流量[GB]，不含单位
     */
    public function enableTrafficInGB(): float
    {
        return Tools::flowToGB($this->transfer_enable);
    }

    /*
     * 已用流量[自动单位]
     */
    public function usedTraffic(): string
    {
        return Tools::flowAutoShow($this->u + $this->d);
    }

    /*
     * 已用流量占总流量的百分比
     */
    public function trafficUsagePercent(): int
    {
        if ($this->transfer_enable == 0) {
            return 0;
        }
        $percent  = ($this->u + $this->d) / $this->transfer_enable;
        $percent  = round($percent, 2);
        $percent *= 100;
        return $percent;
    }

    /*
     * 剩余流量[自动单位]
     */
    public function unusedTraffic(): string
    {
        return Tools::flowAutoShow($this->transfer_enable - ($this->u + $this->d));
    }

    /*
     * 剩余流量占总流量的百分比
     */
    public function unusedTrafficPercent(): int
    {
        if ($this->transfer_enable == 0) {
            return 0;
        }
        $unused   = $this->transfer_enable - ($this->u + $this->d);
        $percent  = $unused / $this->transfer_enable;
        $percent  = round($percent, 2);
        $percent *= 100;
        return $percent;
    }

    /*
     * 今天使用的流量[自动单位]
     */
    public function TodayusedTraffic(): string
    {
        return Tools::flowAutoShow($this->u + $this->d - $this->last_day_t);
    }

    /*
     * 今天使用的流量占总流量的百分比
     */
    public function TodayusedTrafficPercent(): int
    {
        if ($this->transfer_enable == 0) {
            return 0;
        }
        $Todayused = $this->u + $this->d - $this->last_day_t;
        $percent   = $Todayused / $this->transfer_enable;
        $percent   = round($percent, 2);
        $percent  *= 100;
        return $percent;
    }

    /*
     * 今天之前已使用的流量[自动单位]
     */
    public function LastusedTraffic(): string
    {
        return Tools::flowAutoShow($this->last_day_t);
    }

    /*
     * 今天之前已使用的流量占总流量的百分比
     */
    public function LastusedTrafficPercent(): int
    {
        if ($this->transfer_enable == 0) {
            return 0;
        }
        $Lastused = $this->last_day_t;
        $percent  = $Lastused / $this->transfer_enable;
        $percent  = round($percent, 2);
        $percent *= 100;
        return $percent;
    }

    /*
     * @param traffic 单位 MB
     */
    public function addTraffic($traffic)
    {
    }

    /**
     * 获取用户的邀请码
     */
    public function getInviteCodes(): ?InviteCode
    {
        return InviteCode::where('user_id', $this->id)->first();
    }
    
    /**
     * 用户的邀请人
     */
    public function ref_by_user(): ?User
    {
        return self::find($this->ref_by);
    }

    /**
     * 用户邀请人的用户名
     */
    public function ref_by_name(): string
    {
        if ($this->ref_by == 0) {
            return '系统邀请';
        } else {
            if ($this->ref_by_user() == null) {
                return '邀请人已经被删除';
            } else {
                return $this->ref_by_user()->name;
            }
        }
    }

    /**
     * 删除用户的订阅链接
     */
    public function clean_link()
    {
        Link::where('userid', $this->id)->delete();
    }
    
    /**
     * 获取用户的订阅链接
     */
    public function getSublink()
    {
        return LinkController::GenerateSubCode($this->id);
    }

    /**
     * 删除用户的邀请码
     */
    public function clear_inviteCodes()
    {
        InviteCode::where('user_id', $this->id)->delete();
    }

    /**
     * 在线 IP 个数
     */
    public function online_ip_count(): int
    {
        // 根据 IP 分组去重
        $total = Ip::where('datetime', '>=', time() - 90)->where('userid', $this->id)->orderBy('userid', 'desc')->groupBy('ip')->get();
        $ip_list = [];
        foreach ($total as $single_record) {
            $ip = Tools::getRealIp($single_record->ip);
            /*
            if (Node::where('node_ip', $ip)->first() != null) {
                continue;
            }
            */
            $ip_list[] = $ip;
        }
        return count($ip_list);
    }

    /**
     * 销户
     */
    public function deleteUser(): bool
    {
        $uid   = $this->id;
        $email = $this->email;

        DetectBanLog::where('user_id', '=', $uid)->delete();
        DetectLog::where('user_id', '=', $uid)->delete();
        EmailVerify::where('email', $email)->delete();
        InviteCode::where('user_id', '=', $uid)->delete();
        Ip::where('userid', '=', $uid)->delete();
        Link::where('userid', '=', $uid)->delete();
        SigninIp::where('userid', '=', $uid)->delete();
        PasswordReset::where('email', '=', $email)->delete();
        TelegramSession::where('user_id', '=', $uid)->delete();
        Token::where('user_id', '=', $uid)->delete();

        UserSubscribeLog::where('user_id', '=', $uid)->delete();

        $this->delete();

        return true;
    }

    /**
     * 累计充值金额
     */
    public function get_top_up(): float
    {
        $number = Order::where('user_id', $this->id)->where('order_status', 2)->where('order_payment', '!=', 'creditpay')->sum('order_total');
        return is_null($number) ? 0.00 : round($number, 2);
    }

    /**
     * 获取累计收入
     *
     * @param string $req
     */
    public function calIncome(string $req): float
    {
        switch ($req) {
            case "yesterday":
                $number = Order::whereDate('paid_time', '=', date('Y-m-d', strtotime('-1 days')))->sum('order_total');
                break;
            case "today":
                $number = Order::whereDate('paid_time', '=', date('Y-m-d'))->sum('order_total');
                break;
            case "this month":
                $number = Order::whereYear('paid_time', '=', date('Y'))->whereMonth('usedatetime', '=', date('m'))->sum('order_total');
                break;
            case "last month":
                $number = Order::whereYear('paid_time', '=', date('Y'))->whereMonth('paid_time', '=', date('m', strtotime('last month')))->sum('order_total');
                break;
            default:
                $number = Order::sum('order_total');
                break;
        }
        return is_null($number) ? 0.00 : round($number, 2);
    }

    /**
     * 获取付费用户总数
     */
    public function paidUserCount(): int
    {
        return self::where('class', '>', '0')->count();
    }

    public function allUserCount()
    {
        return self::count();
    }

    public function transformation()
    {
        $all = $this->allUserCount();
        $paid = $this->paidUserCount();

        return round($paid / $all * 100) . '%';
    }

    /**
     * 获取用户被封禁的理由
     */
    public function disableReason(): string
    {
        $reason_id = DetectLog::where('user_id', $this->id)->orderBy('id', 'DESC')->first();
        $reason    = DetectRule::find($reason_id->list_id);
        if (is_null($reason)) {
            return '特殊原因被禁用，了解详情请联系管理员';
        }
        return $reason->text;
    }

    /**
     * 最后一次被封禁的时间
     */
    public function last_detect_ban_time(): string
    {
        return ($this->last_detect_ban_time == '1989-06-04 00:05:00' ? '未被封禁过' : $this->last_detect_ban_time);
    }

    /**
     * 当前解封时间
     */
    public function relieve_time(): string
    {
        $logs = DetectBanLog::where('user_id', $this->id)->orderBy('id', 'desc')->first();
        if ($this->enable == 0 && $logs != null) {
            $time = ($logs->end_time + $logs->ban_time * 60);
            return date('Y-m-d H:i:s', $time);
        } else {
            return '当前未被封禁';
        }
    }

    /**
     * 累计被封禁的次数
     */
    public function detect_ban_number(): int
    {
        return DetectBanLog::where('user_id', $this->id)->count();
    }

    /**
     * 最后一次封禁的违规次数
     */
    public function user_detect_ban_number(): int
    {
        $logs = DetectBanLog::where('user_id', $this->id)->orderBy('id', 'desc')->first();
        return $logs->detect_number;
    }

    /**
     * 解绑 Telegram
     */
    public function unbindTelegram(): array
    {
        $return = [
            'ok'  => true,
            'msg' => '解绑成功.'
        ];
        $telegram_id = $this->telegram_id;
        $this->telegram_id = 0;
        if ($this->save()) {
            if (
                Setting::obtain('enable_telegram_bot') == true && !$this->is_admin
            ) {
                \App\Utils\Telegram\TelegramTools::SendPost(
                    'kickChatMember',
                    [
                        'chat_id'   => Setting::obtain('telegram_group_id'),
                        'user_id'   => $telegram_id,
                    ]
                );
            }
        } else {
            $return = [
                'ok'  => false,
                'msg' => '解绑失败.'
            ];
        }

        return $return;
    }

    /**
     * 当前用户产品流量重置周期
     */
    public function userTrafficResetCycle()
    {
        $product = Product::find($this->product_id);
        $cycle = $product->reset_traffic_cycle;
        return $cycle;
    }

    /**
     * 用户下次流量重置时间
     */
    public function productTrafficResetDate()
    {
        if ($this->reset_traffic_date != null) {
            $d = $this->reset_traffic_date;
            $ym = date('Y-m', strtotime('+1 month'));
            $reset_date = $ym.'-'.$d;
            return $reset_date;
        } else {
            return I18n::get()->t('no need to reset');
        }
    }

    /**
     * 发送邮件
     *
     * @param string $subject
     * @param string $template
     * @param array  $ary
     * @param array  $files
     */
    public function sendMail(string $subject, string $template, array $ary = [], array $files = [], $is_queue = false): bool
    {
        $result = false;
        if ($is_queue) {
            $new_emailqueue = new EmailQueue;
            $new_emailqueue->to_email = $this->email;
            $new_emailqueue->subject = $subject;
            $new_emailqueue->template = $template;
            $new_emailqueue->time = time();
            $ary = array_merge(['user' => $this], $ary);
            $new_emailqueue->array = json_encode($ary);
            $new_emailqueue->save();
            return true;
        }
        // 验证邮箱地址是否正确
        if (Tools::isEmail($this->email)) {
            // 发送邮件
            try {
                Mail::send(
                    $this->email,
                    $subject,
                    $template,
                    array_merge(
                        [
                            'user' => $this
                        ],
                        $ary
                    ),
                    $files
                );
                $result = true;
            } catch (Exception $e) {
                echo $e->getMessage();
            }
        }
        return $result;
    }

    /**
     * 发送 Telegram 讯息
     *
     * @param string $text
     */
    public function sendTelegram(string $text): bool
    {
        $result = false;
        if ($this->telegram_id > 0) {
            Telegram::Send(
                $text,
                $this->telegram_id
            );
            $result = true;
        }
        return $result;
    }
    
    /**
     * 记录登录 IP
     *
     * @param string $ip
     * @param int    $type 登录失败为 1
     */
    public function collectSigninIp(string $ip, int $type = 0): bool
    {
        $SigninIp           = new SigninIp();
        $SigninIp->ip       = $ip;
        $SigninIp->userid   = $this->id;
        $SigninIp->datetime = time();
        $SigninIp->type     = $type;

        return $SigninIp->save();
    }

    public function enable()
    {
        $enables = "'enable'";
        switch ($this->enable) {
            case 0:
                $enable = '<div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" value="" id="user_enable_'.$this->id.'" onclick="updateUserStatus('.$enables.', '.$this->id.')" />
                            </div>';
                break;
            case 1:
                $enable = '<div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" value="" id="user_enable_'.$this->id.'" checked="checked" onclick="updateUserStatus('.$enables.', '.$this->id.')" />
                            </div>';
                break;
        }
        return $enable;
    }

    public function is_admin()
    {
        $is_admins = "'is_admin'";
        switch ($this->is_admin) {
            case 0:
                $is_admin = '<div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" value="" id="user_is_admin_'.$this->id.'" onclick="updateUserStatus('.$is_admins.', '.$this->id.')" />
                            </div>';
                break;
            case 1:
                $is_admin = '<div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" value="" id="user_is_admin_'.$this->id.'" checked="checked" onclick="updateUserStatus('.$is_admins.', '.$this->id.')" />
                            </div>';
                break;
        }
        return $is_admin;
    }
}
