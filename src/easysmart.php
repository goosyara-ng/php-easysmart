<?php
/**
 * TP-Link Easy Smart status fetcher for UserSide ERP (PHP version)
 *
 * LICENSE: The software is provided "AS IS", without warranty of any kind,
   express or implied, including but not limited to the warranties of
   merchantability, fitness for a particular purpose and noninfringement.
   In no event shall the authors or copyright holders be liable for any claim,
   damages or other liability, whether in an action of contract, tort or
   otherwise, arising from, out of or in connection with the software or the 
   use or other dealings in the software.
 *
 * @package    php-easysmart
 * @author     Illia Malinich
 * @author     Pavlo Malinich
 * @author     Mykola Tomchuk
 * @link       https://github.com/goosyara-ng/php-easysmart
 */

class TpLinkEasySmart {
    private $api_version = 0;
    private $logon_status = false;
    private $port_middle_num = 0;
    private $max_port_num = 0;
    private $port_type = "geth";
    private $host;

    //
    // UserSide methods
    //
    
    public static function create($host, $username, $password) {
        return new TpLinkEasySmart($host, $username, $password);
    }

    public function interfaces() {
        if(!$this->logon_status)
            throw new Exception('Session timed out');
        switch($this->api_version) {
            case 0:
                return array(); break;
            case 1: 
                return $this->interfaces_v1(); break;
            default:
                return $this->interfaces_v2(); break;
        }
    }
    
    public function vlans() {
        if(!$this->logon_status)
            throw new Exception('Session timed out');
        switch($this->api_version) {
            case 0:
                return array(); break;
            case 1: 
                return $this->vlans_v1(); break;
            default:
                return $this->vlans_v2(); break;
        }
    }

    //
    // Easy Smart methods
    //

    public function __construct($host, $username, $password) {
        $this->host = $host;
        $this->login($username, $password);
    }

    private function login($username, $password) {
        if( $curl = curl_init() ) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_URL, 'http://' . $this->host . '/logon.cgi');
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query( [
                'username' => $username,
                'password' => $password,
                'logon' => 'Login'
            ] ) );
            $auth_body = curl_exec($curl);

            // Check device avaibility
            if( curl_getinfo($curl, CURLINFO_HTTP_CODE) == 0 )
                throw new ConnectionTimeoutException('Switch connection timed out'); // Switch HTTPd unreachable
            curl_close($curl);

            // Check firmware API version
            if( strtolower( substr($auth_body, 0, 7) ) == '<script' )
                $this->api_version = 2; // REV V2+ and higher
            else
                throw new UnsupportedHardwareException('Unsupported hardware'); // Non EasySmart HTTPd
            if (strpos($auth_body, '<BODY class="LOGIN_L"') !== false) {
                $this->api_version = 1; // REV V1 (DE)
            }

            // Parse authentication JS
            $js_vars = TpLinkEasySmart::parse_js(
                TpLinkEasySmart::extract_js($auth_body)
            );
            if( !isset($js_vars['logonInfo']) ) {
                throw new Exception('Unsupported hardware version - REV V1 (E)'); // REV V1 (E) 5 & 8 port switches
            }
            $logon_info = json_decode( $js_vars['logonInfo'] );
            if( intval($logon_info[0]) == 3 )
                throw new Exception('Device busy');
            if( intval($logon_info[0]) == 0 )
                $this->logon_status = true;
            else
                throw new IncorrectCredentialsException('Incorrect switch credentials');
        }
    }
    
    public function logout() {
        $js_vars = TpLinkEasySmart::parse_js(
            TpLinkEasySmart::extract_js( $this->get('/Logout.htm') )
        );
    }

    //
    // Easy Smart REV V1 (DE) methods
    //

    private function interfaces_v1() {
        $speed = array(0, 0, 10, 10, 100, 100, 1000, 0);
        $js_config = TpLinkEasySmart::parse_js( // Load port configuration
            TpLinkEasySmart::extract_js(
                $this->get('/PortSettingRpm.htm'), 4
            ), true
        );
        $this->max_port_num = intval( $js_config['max_port_num'] );
        $js_vars = TpLinkEasySmart::parse_js( // Load ports state
            TpLinkEasySmart::extract_js(
                $this->get('/PortStatisticsRpm.htm'), 4
            ), true
        );
        $this->port_middle_num = intval( $js_vars['port_middle_num'] );
        $ports = array(); if( !isset( $js_vars['tmp_info'] ) ) return $ports;
        $all_info = explode(" ", $js_vars['tmp_info']);
        
        for ($index = 0; $index < $this->port_middle_num; $index++) {
            $port = array(); $port_id = $index + 1;
            $port['ifIndex'] = $port_id;
            $port['name'] = "Port " . $port_id;
            $port['statusAdmin'] = boolval( $all_info[$index * 6] );
            $port['statusOper'] = boolval( $all_info[$index * 6 + 1] );
            $port['type'] = $this->port_type;
            $port['portSpeed'] = $speed[ intval( $all_info[$index * 6 + 1] ) ];
            $port['inOctets'] = intval( $all_info[$index * 6 + 4] );
            $port['outOctets'] = intval( $all_info[$index * 6 + 2] );
            $port['inErrors'] = intval( $all_info[$index * 6 + 5] );
            $port['outErrors'] = intval( $all_info[$index * 6 + 3] );
            $ports[] = $port;
        }
        
        if( ( $this->max_port_num > $this->port_middle_num ) && ( isset( $js_vars['tmp_info2'] ) ) ) {
            $all_info2 = explode(" ", $js_vars['tmp_info2']);
            for ($index = $this->port_middle_num; $index < $this->max_port_num; $index++) {
                $port = array(); $port_id = $index + 1;
                $port['ifIndex'] = $port_id;
                $port['name'] = "Port " . $port_id;
                $port['statusAdmin'] = boolval( $all_info2[($index - $this->port_middle_num) * 6] );
                $port['statusOper'] = boolval( $all_info2[($index - $this->port_middle_num) * 6 + 1] );
                $port['type'] = $this->port_type;
                $port['portSpeed'] = $speed[ intval( $all_info2[($index - $this->port_middle_num) * 6 + 1] ) ];
                $port['inOctets'] = intval( $all_info2[($index - $this->port_middle_num) * 6 + 4] );
                $port['outOctets'] = intval( $all_info2[($index - $this->port_middle_num) * 6 + 2] );
                $port['inErrors'] = intval( $all_info2[($index - $this->port_middle_num) * 6 + 5] );
                $port['outErrors'] = intval( $all_info2[($index - $this->port_middle_num) * 6 + 3] );
                $ports[] = $port;
            }
        }
        return $ports;
    }
    
    private function vlans_v1() {
        $js_vars = TpLinkEasySmart::parse_js(
            TpLinkEasySmart::extract_js(
                $this->get('/Vlan8021QRpm.htm'), 4
            )
        );
        if( !isset( $js_vars['qEnable'] ) ) return array();
        if( intval( $js_vars['qEnable'] ) == 0 ) return array(); // Skip if 802.1Q VLAN is disabled
        $this->max_port_num = intval( $js_vars['portNum'] );
        $vlan_count = intval( $js_vars['qVCount'] ); $vlans = array();
        $vlan_vids = json_decode( $js_vars['qVIDs'], true );
        $vlan_names = json_decode( str_replace("'", "\"", $js_vars['qVNames']), true );
        $vlan_tag = json_decode( $js_vars['qVTagMems_map'], true );
        $vlan_untag = json_decode( $js_vars['qVUnTagMems_map'], true );
        
        for ($index = 0; $index < $vlan_count; $index++) {
            $vlan = array();
            $vlan['vid'] = $vlan_vids[$index];
            $vlan['name'] = $vlan_names[$index];
            $vlan['tag'] = array();
            if( intval($vlan_tag[$index]) != 0 ) {
                $tagged = strrev(str_pad(decbin($vlan_tag[$index]), $this->max_port_num, '0', STR_PAD_LEFT));
                for ($int_id = 0; $int_id < $this->max_port_num; $int_id++) {
                    if( boolval($tagged[$int_id]) )
                        $vlan['tag'][] = $int_id + 1; 
                }
            }
            $vlan['untag'] = array();
            if( intval($vlan_untag[$index]) != 0 ) {
                $untagged = strrev(str_pad(decbin($vlan_untag[$index]), $this->max_port_num, '0', STR_PAD_LEFT));
                for ($int_id = 0; $int_id < $this->max_port_num; $int_id++) {
                    if( boolval($untagged[$int_id]) )
                        $vlan['untag'][] = $int_id + 1; 
                }
            }
            $vlans[] = $vlan;
        }
        return $vlans;
    }

    //
    // Easy Smart REV V2+ methods
    //

    private function interfaces_v2() {
        $speed = array(0, 0, 10, 10, 100, 100, 1000, 0);
        $js_vars = TpLinkEasySmart::parse_js( // Load ports state
            TpLinkEasySmart::extract_js(
                $this->get('/PortStatisticsRpm.htm'), 0
            )
        );
        if( !isset( $js_vars['max_port_num'] ) ||
            !isset( $js_vars['port_middle_num'] ) ||
            !isset( $js_vars['all_info'] ) ) return array();
        $this->max_port_num = intval( $js_vars['max_port_num'] );
        $this->port_middle_num = intval( $js_vars['port_middle_num'] );
        $json_data = preg_replace('/(\w+):/i', '"${1}":', $js_vars['all_info']);
        $ports = array(); $all_info = json_decode( $json_data, true );
        if( !isset( $all_info['state'] ) ||
            !isset( $all_info['link_status'] ) ||
            !isset( $all_info['pkts'] ) ) return array();
        
        for ($index = 0; $index < $this->max_port_num; $index++) {
            $port = array(); $port_id = $index + 1;
            $port['ifIndex'] = $port_id;
            $port['name'] = "Port " . $port_id;
            $port['statusAdmin'] = boolval( $all_info['state'][$index] );
            $port['statusOper'] = boolval( $all_info['link_status'][$index] );
            $port['type'] = $this->port_type;
            $port['portSpeed'] = $speed[ intval( $all_info['link_status'][$index] ) ];
            $port['inOctets'] = intval( $all_info['pkts'][$index * 4 + 2] );
            $port['outOctets'] = intval( $all_info['pkts'][$index * 4 + 0] );
            $port['inErrors'] = intval( $all_info['pkts'][$index * 4 + 3] );
            $port['outErrors'] = intval( $all_info['pkts'][$index * 4 + 1] );
            $ports[] = $port;
        }
        return $ports;
    }
    
    private function vlans_v2() {
        $js_vars = TpLinkEasySmart::parse_js(
            TpLinkEasySmart::extract_js(
                $this->get('/Vlan8021QRpm.htm'), 0
            )
        );
        if( !isset( $js_vars['qvlan_ds'] ) ) return array();
        $json_data = preg_replace('/("(.*?)"|(\w+))(\s*:\s*(".*?"|.))/s', '"$2$3"$4', $js_vars['qvlan_ds']);
        $json_data = preg_replace("/'.*?'(*SKIP)(*FAIL)|0x(\w+)/", '"0x${1}"', $json_data);
        $json_data = str_replace("'", '"', $json_data);
        $qvlan_ds = json_decode( $json_data, true );
        if( !isset( $qvlan_ds['state'] ) ) return array();
        if( intval( $qvlan_ds['state'] ) == 0 ) return array(); // Skip if 802.1Q VLAN is disabled
        $this->max_port_num = intval( $qvlan_ds['portNum'] );
        $vlan_count = intval( $qvlan_ds['count'] ); $vlans = array();

        for ($index = 0; $index < $vlan_count; $index++) {
            $vlan = array();
            $vlan['vid'] = $qvlan_ds['vids'][$index];
            $vlan['name'] = $qvlan_ds['names'][$index];
            $vlan['tag'] = array(); $tag_map = hexdec($qvlan_ds['tagMbrs'][$index]);
            if( $tag_map != 0 ) {
                $tagged = strrev(str_pad(decbin($tag_map), $this->max_port_num, '0', STR_PAD_LEFT));
                for ($int_id = 0; $int_id < $this->max_port_num; $int_id++) {
                    if( boolval($tagged[$int_id]) )
                        $vlan['tag'][] = $int_id + 1; 
                }
            }
            $vlan['untag'] = array(); $untag_map = hexdec($qvlan_ds['untagMbrs'][$index]);
            if( $untag_map != 0 ) {
                $untagged = strrev(str_pad(decbin($untag_map), $this->max_port_num, '0', STR_PAD_LEFT));
                for ($int_id = 0; $int_id < $this->max_port_num; $int_id++) {
                    if( boolval($untagged[$int_id]) )
                        $vlan['untag'][] = $int_id + 1; 
                }
            }
            $vlans[] = $vlan;
        }
        return $vlans;
    }
    
    //
    // JavaScript parsing methods
    //
    
    private static function parse_js($js_code, $ignore_empty = false) {
        if($ignore_empty) $js_code = preg_replace('~var\s+([^=]+?);\s*~imu', '', $js_code);
        preg_match_all('~var\s+([^=]+?)\s*=\s*(.+?)\s*;\s*~isu', $js_code, $js_matches, PREG_SET_ORDER);
        $variables = array();
        foreach ($js_matches as $matches) {
            $val = $matches[2];
            preg_match('/^\s*new\s*array\s*\(\s*(.+?)\s*\)\s*/isu', $matches[2], $array_items);
            if( count($array_items) >= 2 ) {
                $matches[2] = '[' . preg_replace('~[\r\n\t]+~', '', trim($array_items[1])) . ']';
            }
            $variables[$matches[1]] = trim( trim( $matches[2], '"') );
        }
        return $variables;
    }
    
    private static function extract_js($blob, $id = 0) {
        $js_body = new DOMDocument();
        libxml_use_internal_errors(true);
        $js_body->strictErrorChecking = false;
        $js_body->loadHtml($blob);
        $js_tags = $js_body->getElementsByTagName('script');
        if( count($js_tags) < $id + 1 ) return null;
        $js_code = $js_tags[$id]->textContent;
        $comment_pattern = '/(?:(?:\/\*(?:[^*]|(?:\*+[^*\/]))*\*+\/)|(?:(?<!\:|\\\|\')\/\/.*))/';
        return preg_replace($comment_pattern, '', $js_code);
    }
    
    private function get($uri = '/') {
        if( $curl = curl_init() ) {
            if( !preg_match('/^\/.*$/', $uri) ) $uri = '/' . $uri;
            curl_setopt($curl, CURLOPT_HEADER, true);
            curl_setopt($curl, CURLOPT_CRLF, true);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_URL, 'http://' . $this->host . $uri);
            $html = curl_exec($curl);
            curl_close($curl);
            return $html;
        }
    }
}

