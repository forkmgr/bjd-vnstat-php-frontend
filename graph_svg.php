<?php
//
// SVG graph generator — outputs standalone SVG with inline CSS and viewBox for responsiveness
//
include 'config.php';
include 'localize.php';
include 'vnstat.php';

validate_input();
include "./themes/$_style/theme.php";

function _alloc_color($colors)
{
    $c = array();
    $c['rgb']     = sprintf("#%02X%02X%02X", $colors[0], $colors[1], $colors[2]);
    $c['opacity'] = sprintf("%F", (127 - $colors[3]) / 127);
    return $c;
}

function _svg_begin($w, $h)
{
    // viewBox lets the SVG scale to any container width
    header('Content-type: image/svg+xml; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    print '<?xml version="1.0" encoding="UTF-8" standalone="no"?>';
    print "<svg width=\"$w\" height=\"$h\" viewBox=\"0 0 $w $h\" preserveAspectRatio=\"xMidYMid meet\" version=\"1.2\" baseProfile=\"tiny\" xmlns=\"http://www.w3.org/2000/svg\">";
    print '<title>🐴 </title>';
    print "<g style=\"shape-rendering:crispEdges\">";
}

function _svg_end()
{
    print "</g></svg>";
}

function _svg_text($x, $y, $text, $opts = array())
{
    $attrs = '';
    foreach ($opts as $k => $v)
        $attrs .= " $k=\"" . htmlspecialchars(strval($v)) . '"';
    printf("<text x=\"%F\" y=\"%F\"$attrs>%s</text>", $x, $y, htmlspecialchars($text));
}

function _svg_line($x1, $y1, $x2, $y2, $opts = array())
{
    $attrs = '';
    foreach ($opts as $k => $v)
        $attrs .= " $k=\"" . htmlspecialchars(strval($v)) . '"';
    printf("<line x1=\"%F\" y1=\"%F\" x2=\"%F\" y2=\"%F\"$attrs/>", $x1, $y1, $x2, $y2);
}

function _svg_rect($x, $y, $w, $h, $opts = array())
{
    $attrs = '';
    foreach ($opts as $k => $v)
        $attrs .= " $k=\"" . htmlspecialchars(strval($v)) . '"';
    printf("<rect x=\"%F\" y=\"%F\" width=\"%F\" height=\"%F\"$attrs/>", $x, $y, $w, $h);
}

function _svg_poly($pts, $opts = array())
{
    $str = '';
    for ($p = 0; $p < count($pts); $p += 2)
        $str .= sprintf("%F,%F ", $pts[$p], $pts[$p+1]);
    $attrs = '';
    foreach ($opts as $k => $v)
        $attrs .= " $k=\"" . htmlspecialchars(strval($v)) . '"';
    print "<polygon points=\"$str\"$attrs/>";
}

function _svg_group($opts = array())
{
    $attrs = '';
    foreach ($opts as $k => $v)
        $attrs .= " $k=\"" . htmlspecialchars(strval($v)) . '"';
    print "<g$attrs>";
}

function _svg_group_end()
{
    print "</g>";
}

function _init_image()
{
    global $_xlm, $_xrm, $_ytm, $_ybm, $_iw, $_ih;
    global $_graph, $_cl, $_colorscheme; // SVG_DEPTH_SCALING, SVG_FONT are constants
    global $_iface;

    if ($_graph == 'none') return;

    $_xlm = 70;
    $_xrm = 20;
    $_ytm = 35;
    $_ybm = 60;

    if ($_graph == 'small')
    {
        $_iw = 300 + $_xrm + $_xlm;
        $_ih = 100 + $_ytm + $_ybm;
    }
    else
    {
        $_iw = 600 + $_xrm + $_xlm;
        $_ih = 200 + $_ytm + $_ybm;
    }

    _svg_begin($_iw, $_ih);

    $cs = $_colorscheme;
    $_cl = array();
    $_cl['image_bg']   = _alloc_color($cs['image_background']);
    $_cl['bg']         = _alloc_color($cs['graph_background']);
    $_cl['bg2']        = _alloc_color($cs['graph_background_2']);
    $_cl['grid1']      = _alloc_color($cs['grid_stipple_1']);
    $_cl['grid2']      = _alloc_color($cs['grid_stipple_2']);
    $_cl['text']       = _alloc_color($cs['text']);
    $_cl['border']     = _alloc_color($cs['border']);
    $_cl['rx']         = _alloc_color($cs['rx']);
    $_cl['rx_border']  = _alloc_color($cs['rx_border']);
    $_cl['tx']         = _alloc_color($cs['tx']);
    $_cl['tx_border']  = _alloc_color($cs['tx_border']);

    _svg_rect(0, 0, $_iw, $_ih, array('stroke' => 'none', 'stroke-width' => 0, 'fill' => $_cl['image_bg']['rgb']));
    _svg_rect($_xlm, $_ytm, $_iw - $_xrm - $_xlm, $_ih - $_ybm - $_ytm,
              array('stroke' => 'none', 'stroke-width' => 0, 'fill' => $_cl['bg']['rgb']));

    $depth = 12 * SVG_DEPTH_SCALING;
    _svg_group(array('stroke' => 'none', 'stroke-width' => 0, 'fill' => $_cl['bg2']['rgb'], 'fill-opacity' => $_cl['bg2']['opacity']));
    _svg_poly(array($_xlm, $_ytm, $_xlm, $_ih - $_ybm, $_xlm - $depth, $_ih - $_ybm + $depth, $_xlm - $depth, $_ytm + $depth));
    _svg_poly(array($_xlm, $_ih - $_ybm, $_xlm - $depth, $_ih - $_ybm + $depth, $_iw - $_xrm - $depth, $_ih - $_ybm + $depth, $_iw - $_xrm, $_ih - $_ybm));
    _svg_group_end();

    // Title
    $text = h(T('Traffic data for')) . ' ' . h($_iface);
    _svg_text($_iw / 2, $_ytm / 2, $text, array(
        'stroke' => 'none', 'fill' => $_cl['text']['rgb'], 'stroke-width' => 0,
        'font-family' => SVG_FONT, 'font-weight' => 'bold', 'text-anchor' => 'middle',
        'font-size' => '14px'
    ));
}

function _draw_border()
{
    global $_cl, $_iw, $_ih;
    _svg_rect(1, 1, $_iw - 2, $_ih - 2,
              array('stroke' => $_cl['border']['rgb'], 'stroke-opacity' => $_cl['border']['opacity'], 'stroke-width' => 1, 'fill' => 'none'));
}

function _draw_grid($x_ticks, $y_ticks)
{
    global $_cl, $_iw, $_ih, $_xlm, $_xrm, $_ytm, $_ybm; // SVG_DEPTH_SCALING constant

    $x_step = ($_iw - $_xlm - $_xrm) / ($x_ticks ?: 1);
    $y_step = ($_ih - $_ytm - $_ybm) / $y_ticks;
    $depth = 12 * SVG_DEPTH_SCALING;

    _svg_group(array('stroke' => $_cl['grid1']['rgb'], 'stroke-opacity' => $_cl['grid1']['opacity'],
                     'stroke-width' => '1px', 'stroke-dasharray' => '1,1'));
    for ($i = $_xlm; $i <= ($_iw - $_xrm); $i += $x_step)
    {
        _svg_line($i, $_ytm, $i, $_ih - $_ybm);
        _svg_line($i, $_ih - $_ybm, $i - $depth, $_ih - $_ybm + $depth);
    }
    for ($i = $_ytm; $i <= ($_ih - $_ybm); $i += $y_step)
    {
        _svg_line($_xlm, $i, $_iw - $_xrm, $i);
        _svg_line($_xlm, $i, $_xlm - $depth, $i + $depth);
    }
    _svg_group_end();

    _svg_group(array('stroke' => $_cl['border']['rgb'], 'stroke-width' => '1px', 'stroke-opacity' => $_cl['border']['opacity']));
    _svg_line($_xlm, $_ytm, $_xlm, $_ih - $_ybm);
    _svg_line($_xlm, $_ih - $_ybm, $_iw - $_xrm, $_ih - $_ybm);
    _svg_group_end();
}

function _draw_data($data)
{
    global $_cl, $_iw, $_ih, $_xlm, $_xrm, $_ytm, $_ybm; // SVG_DEPTH_SCALING, SVG_FONT constants

    sort($data);
    $x_ticks  = count($data);
    $y_ticks  = 10;
    $y_scale  = 1;
    $prescale = 1;
    $unit     = 'K';
    $offset   = 0;
    $gr_h     = $_ih - $_ytm - $_ybm;
    $x_step   = ($_iw - $_xlm - $_xrm) / ($x_ticks ?: 1);
    $y_step   = ($_ih - $_ytm - $_ybm) / $y_ticks;
    $bar_w    = $x_step / 2;

    // Determine scale
    $low  = 99999999999;
    $high = 0;
    for ($i = 0; $i < $x_ticks; $i++)
    {
        if ($data[$i]['rx'] < $low)  $low  = $data[$i]['rx'];
        if ($data[$i]['tx'] < $low)  $low  = $data[$i]['tx'];
        if ($data[$i]['rx'] > $high) $high = $data[$i]['rx'];
        if ($data[$i]['tx'] > $high) $high = $data[$i]['tx'];
    }

    while ($high > ($prescale * $y_scale * $y_ticks))
    {
        $y_scale *= 2;
        if ($y_scale >= 1024)
        {
            $prescale *= 1024;
            $y_scale  /= 1024;
            if ($unit == 'K') $unit = 'M';
            elseif ($unit == 'M') $unit = 'G';
            elseif ($unit == 'G') $unit = 'T';
        }
    }

    _draw_grid($x_ticks, $y_ticks);
    $sf = ($prescale * $y_scale * $y_ticks) / $gr_h;

    if (count($data) == 0)
    {
        _svg_text($_iw / 2, $_ytm + 80, T('no data available'),
                  array('fill' => $_cl['text']['rgb'], 'stroke-width' => 0, 'font-family' => SVG_FONT,
                        'font-size' => '16px', 'text-anchor' => 'middle'));
    }
    else
    {
        $depth = ($x_ticks < 20) ? 8 * SVG_DEPTH_SCALING : 6 * SVG_DEPTH_SCALING;
        $space = 0;

        for ($i = 0; $i < $x_ticks; $i++)
        {
            $x  = $_xlm + ($i * $x_step);
            $y  = $_ytm + ($_ih - $_ytm - $_ybm) - (($data[$i]['rx'] - $offset) / $sf);

            $x1 = (int)$x;
            $y1 = (int)$y;
            $w  = (int)($bar_w - $space);
            $h  = (int)($_ih - $_ybm - $y);
            $x2 = (int)($x + $bar_w - $space);
            $y2 = (int)($_ih - $_ybm);

            // RX bar (incoming)
            _svg_group(array('stroke' => $_cl['rx_border']['rgb'], 'stroke-opacity' => $_cl['rx_border']['opacity'],
                             'stroke-width' => 1, 'stroke-linejoin' => 'round',
                             'fill' => $_cl['rx']['rgb'], 'fill-opacity' => $_cl['rx']['opacity']));
            _svg_rect($x1, $y1, $w, $h);
            _svg_rect($x1 - $depth, $y1 + $depth, $w, $h);
            _svg_poly(array($x1, $y1, $x2, $y1, $x2 - $depth, $y1 + $depth, $x1 - $depth, $y1 + $depth));
            _svg_poly(array($x2, $y1, $x2, $y2, $x2 - $depth, $y2 + $depth, $x2 - $depth, $y1 + $depth));
            _svg_group_end();

            // TX bar (outgoing)
            $y1 = (int)($_ytm + ($_ih - $_ytm - $_ybm) - (($data[$i]['tx'] - $offset) / $sf));
            $x1 = (int)($x1 + $bar_w);
            $x2 = (int)($x2 + $bar_w);
            $w  = (int)($bar_w - $space);
            $h  = (int)($_ih - $_ybm - $y1 - 1);

            _svg_group(array('stroke' => $_cl['tx_border']['rgb'], 'stroke-opacity' => $_cl['tx_border']['opacity'],
                             'stroke-width' => 1, 'stroke-linejoin' => 'round',
                             'fill' => $_cl['tx']['rgb'], 'fill-opacity' => $_cl['tx']['opacity']));
            _svg_rect($x1, $y1, $w, $h);
            _svg_rect($x1 - $depth, $y1 + $depth, $w, $h);
            _svg_poly(array($x1, $y1, $x2, $y1, $x2 - $depth, $y1 + $depth, $x1 - $depth, $y1 + $depth));
            _svg_poly(array($x2, $y1, $x2, $y2, $x2 - $depth, $y2 + $depth, $x2 - $depth, $y1 + $depth));
            _svg_group_end();
        }

        // Y-axis labels
        _svg_group(array('fill' => $_cl['text']['rgb'], 'fill-opacity' => $_cl['text']['opacity'],
                         'stroke-width' => 0, 'font-family' => SVG_FONT, 'font-size' => '10px', 'text-anchor' => 'end'));
        for ($i = 0; $i <= $y_ticks; $i++)
        {
            $label = ($i * $y_scale) . $unit;
            $tx = $_xlm - 16;
            $ty = (int)(($_ih - $_ybm) - ($i * $y_step) + 8 + $depth);
            _svg_text($tx, $ty, $label);
        }
        _svg_group_end();

        // X-axis labels
        _svg_group(array('fill' => $_cl['text']['rgb'], 'fill-opacity' => $_cl['text']['opacity'],
                         'stroke-width' => 0, 'font-family' => SVG_FONT, 'font-size' => '10px', 'text-anchor' => 'middle'));
        for ($i = 0; $i < $x_ticks; $i++)
        {
            $label = $data[$i]['img_label'];
            _svg_text($_xlm + ($i * $x_step) + ($x_step / 2) - $depth - 4, $_ih - $_ybm + 20 + $depth, $label);
        }
        _svg_group_end();
    }

    _draw_border();

    // Legend
    _svg_rect($_xlm, $_ih - $_ybm + 39, 8, 8,
              array('stroke' => $_cl['text']['rgb'], 'stroke-width' => 1, 'fill' => $_cl['rx']['rgb']));
    _svg_text($_xlm + 14, $_ih - $_ybm + 48, T('bytes in'),
              array('fill' => $_cl['text']['rgb'], 'stroke-width' => 0, 'font-family' => SVG_FONT, 'font-size' => '8px'));

    _svg_rect($_xlm + 120, $_ih - $_ybm + 39, 8, 8,
              array('stroke' => $_cl['text']['rgb'], 'stroke-width' => 1, 'fill' => $_cl['tx']['rgb']));
    _svg_text($_xlm + 134, $_ih - $_ybm + 48, T('bytes out'),
              array('fill' => $_cl['text']['rgb'], 'stroke-width' => 0, 'font-family' => SVG_FONT, 'font-size' => '8px'));
}

function _output()
{
    global $_page, $_hour, $_day, $_month;

    if ($_page == 's') return;

    _init_image();

    if ($_page == 'h')      _draw_data($_hour);
    elseif ($_page == 'd')  _draw_data($_day);
    elseif ($_page == 'm')  _draw_data($_month);

    _svg_end();
}

get_vnstat_data();
_output();
?>