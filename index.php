<?php
//
// vnStat PHP frontend — main HTML page
//
include 'config.php';
include 'localize.php';
include 'vnstat.php';

validate_input();
include "./themes/$_style/theme.php";

function _kbytes_to_string($kb)
{
    global $byte_notation;

    $units = array('TB', 'GB', 'MB', 'KB');
    $scale = 1024 * 1024 * 1024;
    $ui = 0;

    $custom_size = isset($byte_notation) && _in_array($byte_notation, $units);

    while ((($kb < $scale) && ($scale > 1)) || $custom_size)
    {
        $ui++;
        $scale = $scale / 1024;
        if ($custom_size && $units[$ui] == $byte_notation)
            break;
    }

    return sprintf("%0.2f %s", $kb / $scale, $units[$ui]);
}

function _write_sidebar()
{
    global $_iface, $_page, $_graph, $_script, $_style;
    global $iface_list, $iface_title;
    global $_page_list, $_page_title;

    $qp = "graph=$_graph&style=$_style";

    // Mobile: select-based navigation
    print '<div class="sidebar-select">';
    print '<label for="iface-select">' . T('Interface') . ':</label> ';
    print '<select id="iface-select" onchange="window.location.href=\'?' . rawurlencode('if') . '=\' + this.value + \'&' . $qp . '\'">';
    foreach ($iface_list as $if)
    {
        $sel = ($if == $_iface) ? ' selected' : '';
        $label = isset($iface_title[$if]) ? $iface_title[$if] : $if;
        print "<option value=\"$if\"$sel>$label ($if)</option>";
    }
    print '</select>';
    print '</div>';

    // Desktop: sidebar
    print '<nav class="sidebar-desktop">';
    print '<h3 class="sidebar-title">' . T('Network interfaces') . '</h3>';
    print '<ul class="iface-list">';
    foreach ($iface_list as $if)
    {
        $cls = ($if == $_iface) ? ' class="active"' : '';
        $label = isset($iface_title[$if]) ? $iface_title[$if] : $if;
        print "<li$cls><a href=\"$_script?if=$if&$qp\"><span class=\"iface-name\">$label</span><span class=\"iface-dev\">$if</span></a>";
        print '<ul class="page-list">';
        foreach ($_page_list as $pg)
        {
            $pcls = ($pg == $_page) ? ' class="active"' : '';
            print "<li$pcls><a href=\"$_script?if=$if&$qp&page=$pg\">{$_page_title[$pg]}</a></li>";
        }
        print '</ul></li>';
    }
    print '</ul>';
    print '</nav>';
}

function _write_summary_table($caption, $data)
{
    if (count($data) == 0) return;

    print '<div class="data-table-wrapper">';
    print '<table class="traffic-table">';
    print "<caption>$caption</caption>";
    print '<thead><tr>';
    print '<th class="col-label">' . T('Period') . '</th>';
    print '<th class="col-rx">'    . T('In')    . '</th>';
    print '<th class="col-tx">'    . T('Out')   . '</th>';
    print '<th class="col-total">' . T('Total') . '</th>';
    print '</tr></thead><tbody>';

    $i = 0;
    foreach ($data as $row)
    {
        if ($row['act'] != 1) continue;
        $cls  = ($i & 1) ? 'odd' : 'even';
        $rx   = _kbytes_to_string($row['rx']);
        $tx   = _kbytes_to_string($row['tx']);
        $total = _kbytes_to_string($row['rx'] + $row['tx']);
        print "<tr class=\"$cls\">";
        print "<td class=\"col-label\" data-label=\"" . T('Period') . "\">{$row['label']}</td>";
        print "<td class=\"col-rx\"    data-label=\"" . T('In')    . "\">$rx</td>";
        print "<td class=\"col-tx\"    data-label=\"" . T('Out')   . "\">$tx</td>";
        print "<td class=\"col-total\" data-label=\"" . T('Total') . "\">$total</td>";
        print '</tr>';
        $i++;
    }
    print '</tbody></table>';
    print '</div>';
}

function _write_summary()
{
    global $_summary, $_top, $_day, $_hour, $_month;

    $trx = (isset($_summary['totalrx']) ? $_summary['totalrx'] : 0) * 1024 + (isset($_summary['totalrxk']) ? $_summary['totalrxk'] : 0);
    $ttx = (isset($_summary['totaltx']) ? $_summary['totaltx'] : 0) * 1024 + (isset($_summary['totaltxk']) ? $_summary['totaltxk'] : 0);

    $sum = array();
    if (count($_day) > 0 && count($_hour) > 0 && count($_month) > 0)
    {
        $sum[0] = array('act' => 1, 'label' => T('This hour'),  'rx' => $_hour[0]['rx'],  'tx' => $_hour[0]['tx']);
        $sum[1] = array('act' => 1, 'label' => T('This day'),   'rx' => $_day[0]['rx'],   'tx' => $_day[0]['tx']);
        $sum[2] = array('act' => 1, 'label' => T('This month'), 'rx' => $_month[0]['rx'], 'tx' => $_month[0]['tx']);
        $sum[3] = array('act' => 1, 'label' => T('All time'),   'rx' => $trx,              'tx' => $ttx);
    }

    _write_summary_table(T('Summary'), $sum);
    print '<br>';
    _write_summary_table(T('Top 10 days'), $_top);
}

function _write_data_table($caption, $data)
{
    _write_summary_table($caption, $data);
}

// --- main ---
get_vnstat_data(true);
header('Content-type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'");
print '<!DOCTYPE html>';
?><html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>🐴 <?php print h(T('Traffic data for')) . ' ' . (isset($iface_title[$_iface]) ? h($iface_title[$_iface]) : h($_iface)); ?> — vnStat</title>
  <link rel="stylesheet" href="themes/<?php echo $_style ?>/style.css" id="theme-css">
</head>
<body>
<div id="page">
  <header id="topbar">
    <div class="topbar-inner">
      <button id="nav-toggle" aria-label="Toggle navigation">☰</button>
      <h1 class="topbar-title"><?php
        $label = isset($iface_title[$_iface]) ? $iface_title[$_iface] : $_iface;
        print T('Traffic data for') . " <span class=\"iface-label\">$label</span> <small>($_iface)</small>";
      ?></h1>
      <div class="topbar-nav">
        <select onchange="window.location.href='?'+this.value">
          <option value=""><?php print T('Page'); ?>...</option>
          <?php
          $qp = "if=$_iface&graph=$_graph&style=$_style";
          $page_codes = array('s', 'h', 'd', 'm');
          foreach ($page_codes as $pc)
              print "<option value=\"$qp&page=$pc\"" . ($pc == $_page ? ' selected' : '') . ">" . T($_page_title[$pc]) . "</option>";
          ?><option disabled>───</option><?php
          if (count($graph_list) > 1)
          {
              foreach ($graph_list as $g)
                  print "<option value=\"?if=$_iface&page=$_page&graph=$g&style=$_style\""
                       . ($g == $_graph ? ' selected' : '') . ">Graph: $g</option>";
          }
          ?>
        </select>
      </div>
    </div>
  </header>

  <div id="layout">
    <aside id="sidebar" aria-label="Sidebar navigation">
      <?php _write_sidebar(); ?>
      <div class="theme-switcher">
        <h4>Theme</h4>
        <?php
        $themes = array('light', 'dark');
        foreach ($themes as $t)
        {
            $cls = ($t == $_style) ? ' class="active"' : '';
            $qp_t = "if=$_iface&page=$_page&graph=$_graph&style=$t";
            print "<a$cls href=\"$_script?$qp_t\">$t</a> ";
        }
        ?>
      </div>
    </aside>

    <main id="content">

      <?php
      // Graph
      if ($_page != 's' && $_graph != 'none' && $graph_format == 'svg')
      {
          $graph_params = "if=$_iface&page=$_page&style=$_style";
          print '<div class="graph-container">';
          print '<div class="graph-svg" id="traffic-graph">';
          // Responsive SVG: use viewBox and let CSS size it
          print '<object type="image/svg+xml" data="graph_svg.php?' . rawurlencode($graph_params) . '">';
          print T('Traffic graph');
          print '</object>';
          print '</div></div>';
      }
      elseif ($_page != 's' && $_graph != 'none' && $graph_format == 'png')
      {
          $graph_params = "if=$_iface&page=$_page&style=$_style";
          print '<div class="graph-container">';
          print '<img src="graph.php?' . rawurlencode($graph_params) . '" alt="' . T('Traffic graph') . '" class="graph-png">';
          print '</div>';
      }

      // Data table
      if ($_page == 's')
          _write_summary();
      else if ($_page == 'h')
          _write_data_table(T('Last 24 hours'), $_hour);
      else if ($_page == 'd')
          _write_data_table(T('Last 30 days'), $_day);
      else if ($_page == 'm')
          _write_data_table(T('Last 12 months'), $_month);
      ?>

    </main>
  </div>

  <footer id="footer">
    <p>
      <a href="https://github.com/forkmgr/bjd-vnstat-php-frontend">vnStat PHP frontend</a>
      &mdash; &copy;2006-2011 Bjorge Dijkstra, CasjaysDev &copy; 2026
      &mdash; powered by <a href="https://humdi.net/vnstat/">vnStat</a>
    </p>
  </footer>
</div>

<script>
// Mobile sidebar toggle
document.getElementById('nav-toggle')?.addEventListener('click', function() {
  document.getElementById('sidebar')?.classList.toggle('open');
});
</script>
</body>
</html>
<?php ?>