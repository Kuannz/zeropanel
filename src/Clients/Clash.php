<?php

namespace App\Clients;

use Symfony\Component\Yaml\Yaml;
use App\Models\Setting;
use App\Models\Node;

class Clash
{
    public $flag = 'clash';
    private $user;
    private $servers;

    public function __construct($user, $servers)
    {
        $this->user = $user;
        $this->servers = $servers;
    }

    public function  handle()
    {
        $user = $this->user;
        $servers = $this->servers;
        $website_name = Setting::obtain('website_name');
        header("subscription-userinfo: upload={$user->u}; download={$user->d}; total={$user->transfer_enable}; expire=".strtotime($user->class_expire));
        header('profile-update-interval: 24');
        header("content-disposition:attachment;filename*=UTF-8''".rawurlencode($website_name).".yaml");
        header("profile-web-page-url:" . Setting::obtain('website_url'));
        $clash_config = dirname(__FILE__,3).'/resources/conf/clash/clash.yaml';
        $config = Yaml::parseFile($clash_config);
        $proxy = [];
        $proxies = [];

        foreach ($servers as $server) {
            $type = $server['type'];
            $buildMethod = 'build' . ucfirst($type);
            $isValidServer = true;
        
            if ($type === 'shadowsocks' && !Node::getShadowsocksSupportMethod($server['method'])) {
                $isValidServer = false;
            }
        
            if ($isValidServer && method_exists($this, $buildMethod)) {
                array_push($proxy, $this->$buildMethod($server));
                array_push($proxies, $server['remark']);
            }
        }

        $config['proxies'] = array_merge($config['proxies'] ?? [], $proxy);

        foreach ($config['proxy-groups'] as &$group) {
            $group['proxies'] = $group['proxies'] ?? [];

            $isFilter = false;
            $newProxies = [];
            foreach ($group['proxies'] as $src) {
                if (!$this->isRegex($src)) {
                    $newProxies[] = $src;
                    continue;
                }

                $isFilter = true;
                foreach ($proxies as $dst) {
                    if ($this->isMatch($src, $dst)) {
                        $newProxies[] = $dst;
                    }
                }
            }
            $group['proxies'] = $isFilter ? $newProxies : array_merge($group['proxies'], $proxies);
        }
        unset($group);

        $config['proxy-groups'] = array_filter($config['proxy-groups'], function($group) {
            return !empty($group['proxies']);
        });

        $subsDomain = $_SERVER['HTTP_HOST'];
        if ($subsDomain) {
            array_unshift($config['rules'], "DOMAIN,{$subsDomain},DIRECT");
        }

        $yaml = Yaml::dump($config, 5, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
        return $yaml;
    }

    public static function buildShadowsocks($server)
    {
        $node_info = [
            'name'     => $server['remark'],
            'type'     => 'ss',
            'server'   => $server['address'],
            'port'     => $server['port'],
            'cipher'   => $server['method'],
            'password' => $server['passwd'],
            'udp'      => true
        ];

        return $node_info;
    }

    public static function buildVmess($server)
    {
        $ws = $server['net'] == 'ws' ? 'ws' : '';
        $tls = $server['security'] == 'tls' ? true : false;
        $node_info = [
            'name'             => $server['remark'],
            'type'             => 'vmess',
            'server'           => $server['address'],
            'port'             => $server['port'],
            'uuid'             => $server['uuid'],
            'alterId'          => $server['aid'],
            'cipher'           => 'auto',
            'udp'              => true,
            'servername'       => $server['host'],
            'network'          => $ws,
            'tls'              => $tls,
            'skip-cert-verify' => true,
            'ws-opts'          => [
                'path'    => $server['path'],
                'headers' => [
                    'Host' => $server['host'],
                ],
            ],
            'grpc-opts' =>  [
                'grpc-service-name' => $server['servicename'],
            ],
        ];

        return $node_info;
    }

    public static function buildTrojan($server)
    {
        $node_info = [
            'name'     => $server['remark'],
            'type'     => 'trojan',
            'server'   => $server['address'],
            'port'     => $server['port'],
            'password' => $server['uuid'],
            'sni'      => $server['sni'],
            'udp'      => true
        ];

        return $node_info;
    }

    private function isMatch($exp, $str)
    {
        return @preg_match($exp, $str);
    }

    private function isRegex($exp)
    {
        return @preg_match($exp, '') !== false;
    }
}