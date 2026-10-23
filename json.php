<?php
//
// JSON data endpoint — returns parsed vnstat data as JSON
//
include 'config.php';
include 'localize.php';
include 'vnstat.php';

validate_input();
include "./themes/$_style/theme.php";

function _write_summary()
{
    global $_summary, $_top, $_day, $_hour, $_month;

    $trx = $_summary['totalrx'] * 1024 + $_summary['totalrxk'];
    $ttx = $_summary['totaltx'] * 1024 + $_summary['totaltxk'];

    $sum = array();
    if (count($_day) > 0 && count($_hour) > 0 && count($_month) > 0)
    {
        $sum['hour']    = array('act' => 1, 'rx' => $_hour[0]['rx'],  'tx' => $_hour[0]['tx']);
        $sum['day']     = array('act' => 1, 'rx' => $_day[0]['rx'],   'tx' => $_day[0]['tx']);
        $sum['month']   = array('act' => 1, 'rx' => $_month[0]['rx'], 'tx' => $_month[0]['tx']);
        $sum['total']   = array('act' => 1, 'rx' => $trx,              'tx' => $ttx);
    }

    print json_encode($sum);
}

get_vnstat_data(false);
header('Content-type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('X-Content-Type-Options: nosniff');

// Convert associative arrays to sequential for JSON output
function _seq($assoc)
{
    $keys = array_keys($assoc);
    rsort($keys);
    $out = array();
    foreach ($keys as $k)
        $out[count($out)] = $assoc[$k];
    return $out;
}

$_hour  = _seq($_hour);
$_day   = _seq($_day);
$_month = _seq($_month);

$qp = "if=$_iface&page=$_page&style=$_style";

if ($_page == 's')
    _write_summary();
elseif ($_page == 'h')
    print json_encode(array('hours' => $_hour));
elseif ($_page == 'd')
    print json_encode(array('days' => $_day));
elseif ($_page == 'm')
    print json_encode(array('months' => $_month));
?>