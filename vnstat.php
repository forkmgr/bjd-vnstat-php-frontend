<?php
//
// vnStat PHP frontend — data layer
//

$_page_list  = array('s', 'h', 'd', 'm');
$graph_list = array('large', 'small', 'none');

$_page_title['s'] = T('summary');
$_page_title['h'] = T('hours');
$_page_title['d'] = T('days');
$_page_title['m'] = T('months');

function _in_array($needle, $haystack)
{
    if (!isset($haystack) || count($haystack) == 0) return false;
    foreach ($haystack as $item)
        if ($item == $needle) return true;
    return false;
}

function validate_input()
{
    global $_page, $_page_list;
    global $_iface, $iface_list;
    global $_graph, $graph_list;
    global $_style;

    $_page  = isset($_GET['page'])  ? $_GET['page']  : '';
    $_iface = isset($_GET['if'])    ? $_GET['if']    : '';
    $_graph = isset($_GET['graph']) ? $_GET['graph'] : '';
    $_style = isset($_GET['style']) ? $_GET['style'] : '';

    if (!_in_array($_page, $_page_list))
        $_page = $_page_list[0];

    if (!_in_array($_iface, $iface_list))
        $_iface = $iface_list[0];

    if (!_in_array($_graph, $graph_list))
        $_graph = $graph_list[0];

    $tp = "./themes/$_style";
    if (!is_dir($tp) || !file_exists("$tp/theme.php") || !preg_match('/^[a-z0-9-_]+$/i', $_style))
        $_style = DEFAULT_COLORSCHEME;
}

//
// Fetch raw vnstat data — supports two source types:
//   1. 'dumpdb' — legacy vnstat --dumpdb pipe (semicolon CSV)
//   2. 'json'   — vnstat 2.x+ --json pipe
//
function _fetch_vnstat_raw($iface)
{
    global $vnstat_bin, $data_dir, $source_format;

    if (!isset($vnstat_bin) || $vnstat_bin == '')
    {
        // File-based dump
        $path = "$data_dir/vnstat_dump_$iface";
        if (file_exists($path))
            return file($path);
        $path2 = "$data_dir/vnstat_json_$iface";
        if ($source_format == 'json' && file_exists($path2))
            return file($path2);
        return array();
    }

    // Pipe from vnstat binary with timeout (prevents hang)
    $flag = ($source_format == 'json') ? '--json' : '--dumpdb';
    $cmd = escapeshellcmd($vnstat_bin) . " $flag -i " . escapeshellarg($iface);
    $descriptorspec = array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
    $process = proc_open($cmd, $descriptorspec, $pipes, null, null, array('bypass_shell' => true));
    if (!is_resource($process))
        return array();

    // Limit execution to 5 seconds
    $buffer = '';
    $timeout = 5;
    $start = time();
    stream_set_blocking($pipes[1], false);
    while (time() - $start < $timeout) {
        $chunk = fread($pipes[1], 4096);
        if ($chunk !== false && strlen($chunk) > 0) {
            $buffer .= $chunk;
            continue;
        }
        usleep(50000); // 50ms
    }
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    return explode("\n", $buffer);
}

//
// Parse vnstat --dumpdb output (legacy format)
// Format: type;id;timestamp;rx;tx;rxk;txk;active
//
function _parse_dumpdb($lines)
{
    $day    = array();
    $hour   = array();
    $month  = array();
    $top    = array();
    $summary = array();

    if (count($lines) > 0 && strpos($lines[0], 'Error') !== false)
        return array($hour, $day, $month, $top, $summary);

    foreach ($lines as $line)
    {
        $d = explode(';', trim($line));
        if (count($d) < 2) continue;

        if ($d[0] == 'd')
        {
            $day[$d[1]]['time']  = $d[2];
            $day[$d[1]]['rx']    = $d[3] * 1024 + $d[5];
            $day[$d[1]]['tx']    = $d[4] * 1024 + $d[6];
            $day[$d[1]]['act']   = $d[7];
        }
        else if ($d[0] == 'm')
        {
            $month[$d[1]]['time'] = $d[2];
            $month[$d[1]]['rx']   = $d[3] * 1024 + $d[5];
            $month[$d[1]]['tx']   = $d[4] * 1024 + $d[6];
            $month[$d[1]]['act']  = $d[7];
        }
        else if ($d[0] == 'h')
        {
            $hour[$d[1]]['time'] = $d[2];
            $hour[$d[1]]['rx']   = $d[3];
            $hour[$d[1]]['tx']   = $d[4];
            $hour[$d[1]]['act']  = 1;
        }
        else if ($d[0] == 't')
        {
            $top[$d[1]]['time'] = $d[2];
            $top[$d[1]]['rx']   = $d[3] * 1024 + $d[5];
            $top[$d[1]]['tx']   = $d[4] * 1024 + $d[6];
            $top[$d[1]]['act']  = $d[7];
        }
        else
        {
            $summary[$d[0]] = isset($d[1]) ? $d[1] : '';
        }
    }

    return array($hour, $day, $month, $top, $summary);
}

//
// Parse vnstat 2.x --json output
// Format: { "interfaces": [{ "name": "eth0", "traffic": { ... } }] }
//
function _parse_json($raw)
{
    $data = @json_decode($raw, true);
    if (!$data || !isset($data['interfaces'][0]['traffic']))
        return array(array(), array(), array(), array(), array());

    $t = $data['interfaces'][0]['traffic'];
    $hour   = array();
    $day    = array();
    $month  = array();
    $top    = array();
    $summary = array();

    // Total summary
    if (isset($t['total'])) {
        $summary['totalrx']  = intval($t['total']['rx'] / (1024*1024*1024));
        $summary['totalrxk'] = intval(($t['total']['rx'] % (1024*1024*1024)) / 1024);
        $summary['totaltx']  = intval($t['total']['tx'] / (1024*1024*1024));
        $summary['totaltxk'] = intval(($t['total']['tx'] % (1024*1024*1024)) / 1024);
    }

    // Hours
    if (isset($t['hour'])) {
        foreach ($t['hour'] as $h) {
            $hour[$h['id']] = array(
                'time' => mktime($h['time']['hour'], 0, 0, $h['date']['month'], $h['date']['day'], $h['date']['year']),
                'rx'   => intval($h['rx'] / 1024),
                'tx'   => intval($h['tx'] / 1024),
                'act'  => 1,
            );
        }
    }

    // Days
    if (isset($t['day'])) {
        foreach ($t['day'] as $d) {
            $id = $d['date']['year'] * 10000 + $d['date']['month'] * 100 + $d['date']['day'];
            $day[$id] = array(
                'time' => mktime(0, 0, 0, $d['date']['month'], $d['date']['day'], $d['date']['year']),
                'rx'   => intval($d['rx'] / 1024),
                'tx'   => intval($d['tx'] / 1024),
                'act'  => 1,
            );
        }
    }

    // Months
    if (isset($t['month'])) {
        foreach ($t['month'] as $m) {
            $id = $m['date']['year'] * 100 + $m['date']['month'];
            $month[$id] = array(
                'time' => mktime(0, 0, 0, $m['date']['month'], 1, $m['date']['year']),
                'rx'   => intval($m['rx'] / 1024),
                'tx'   => intval($m['tx'] / 1024),
                'act'  => 1,
            );
        }
    }

    // Top days
    if (isset($t['top'])) {
        foreach ($t['top'] as $d) {
            $id = $d['date']['year'] * 10000 + $d['date']['month'] * 100 + $d['date']['day'];
            $top[$id] = array(
                'time' => mktime(0, 0, 0, $d['date']['month'], $d['date']['day'], $d['date']['year']),
                'rx'   => intval($d['rx'] / 1024),
                'tx'   => intval($d['tx'] / 1024),
                'act'  => 1,
            );
        }
    }

    return array($hour, $day, $month, $top, $summary);
}

//
// Apply localization labels to parsed data
//
function _apply_labels($hour, $day, $month, $top)
{
    // Days
    $keys = array_keys($day);
    rsort($keys);
    $new_day = array();
    foreach ($keys as $k)
    {
        $d = $day[$k];
        $d['key'] = $k;
        $new_day[count($new_day)] = $d;
        if ($d['time'] != 0)
        {
            $d['label'] = _strftime(T('datefmt_days'), $d['time']);
            $d['img_label'] = _strftime(T('datefmt_days_img'), $d['time']);
        } else {
            $d['label'] = '';
            $d['img_label'] = '';
        }
    }

    // Months
    $keys = array_keys($month);
    rsort($keys);
    $new_month = array();
    foreach ($keys as $k)
    {
        $d = $month[$k];
        $d['key'] = $k;
        $new_month[count($new_month)] = $d;
        if ($d['time'] != 0)
        {
            $d['label']  = _strftime(T('datefmt_months'), $d['time']);
            $d['img_label'] = _strftime(T('datefmt_months_img'), $d['time']);
        } else {
            $d['label'] = '';
            $d['img_label'] = '';
        }
    }

    // Hours
    $keys = array_keys($hour);
    rsort($keys);
    $new_hour = array();
    foreach ($keys as $k)
    {
        $d = $hour[$k];
        $d['key'] = $k;
        if ($d['time'] != 0)
        {
            $st = $d['time'] - ($d['time'] % 3600);
            $et = $st + 3600;
            $d['label']     = _strftime(T('datefmt_hours'), $st) . ' - ' . _strftime(T('datefmt_hours'), $et);
            $d['img_label'] = _strftime(T('datefmt_hours_img'), $d['time']);
        } else {
            $d['label'] = '';
            $d['img_label'] = '';
        }
        $new_hour[count($new_hour)] = $d;
    }

    // Top days
    $keys = array_keys($top);
    rsort($keys);
    $new_top = array();
    foreach ($keys as $k)
    {
        $d = $top[$k];
        $d['key'] = $k;
        $d['label']   = _strftime(T('datefmt_top'), $d['time']);
        $d['img_label'] = '';
        $new_top[count($new_top)] = $d;
    }

    return array($new_hour, $new_day, $new_month, $new_top);
}

//
// Public entry point — fetches and parses vnstat data
//
function get_vnstat_data($use_label = true)
{
    global $_iface, $_hour, $_day, $_month, $_top, $_summary;
    global $vnstat_bin, $data_dir, $source_format;

    $lines = _fetch_vnstat_raw($_iface);
    if ($source_format == 'json') {
        $raw = is_array($lines) ? implode("\n", $lines) : '';
        $parsed = _parse_json($raw);
    } else {
        $parsed = _parse_dumpdb($lines);
    }
    $_hour    = $parsed[0];
    $_day     = $parsed[1];
    $_month   = $parsed[2];
    $_top     = $parsed[3];
    $_summary = $parsed[4];

    if ($use_label)
    {
        $labeled = _apply_labels($_hour, $_day, $_month, $_top);
        $_hour  = $labeled[0];
        $_day   = $labeled[1];
        $_month = $labeled[2];
        $_top   = $labeled[3];
    }
}
?>