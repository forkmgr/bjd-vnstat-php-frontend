<?php
//
// vnStat PHP frontend (c)2006-2010 Bjorge Dijkstra (bjd@jooz.net)
// CasjaysDev (c)2026
//
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.
//
// see file COPYING for more information.
//
error_reporting(E_ALL | E_NOTICE);

//
// configuration parameters
// edit these to reflect your particular situation
//
// Display locale
$locale = 'en_US.UTF-8';
$language = 'en';

// Set local timezone
date_default_timezone_set("America/New_York");

// list of network interfaces monitored by vnStat
$iface_list = array('eth0');

// optional names for interfaces
// if there's no name set for an interface then the interface identifier
// will be displayed instead
$iface_title['eth0'] = 'Internal';

//
// There are two possible sources for vnstat data. If the $vnstat_bin
// variable is set then vnstat is called directly from the PHP script
// to get the interface data.
//
// The other option is to periodically dump the vnstat interface data to
// a file (e.g. by a cronjob). In that case the $vnstat_bin variable
// must be cleared and set $data_dir to the location where the dumps
// are stored. Dumps must be named 'vnstat_dump_$iface'.
//
// You can generate vnstat dumps with the command:
//   vnstat --dumpdb -i $iface > /path/to/data_dir/vnstat_dump_$iface
//
// For vnstat 2.x+, JSON output is also supported:
//   vnstat --json -i $iface 2>/dev/null
//
$vnstat_bin = '/usr/bin/vnstat';
$data_dir = '/var/lib/vnstat';

//
// Data source format: 'dumpdb' (legacy) or 'json' (vnstat 2.x+)
// Set to 'json' for vnstat --json output
//
$source_format = 'json';

// graphics format to use: svg or png
$graph_format = 'svg';

// preferred byte notation. null auto chooses. otherwise use one of
// 'TB','GB','MB','KB'
$byte_notation = null;

// Font to use for PNG graphs (absolute path)
define('GRAPH_FONT', dirname(__FILE__) . '/VeraBd.ttf');

// Font to use for SVG graphs
define('SVG_FONT', 'Verdana');

// Default theme
define('DEFAULT_COLORSCHEME', 'dark');

// SVG Depth scaling factor
define('SVG_DEPTH_SCALING', 1);

// Available graph sizes
$graph_list = array('large', 'small', 'none');
?>