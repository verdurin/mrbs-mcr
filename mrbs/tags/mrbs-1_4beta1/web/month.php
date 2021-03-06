<?php
// $Id$

// mrbs/month.php - Month-at-a-time view

require_once "grab_globals.inc.php";
include "config.inc.php";
include "functions.inc";
include "$dbsys.inc";
include "mrbs_auth.inc";
include "mincals.inc";

// Get form variables
$month = get_form_var('month', 'int');
$year = get_form_var('year', 'int');
$area = get_form_var('area', 'int');
$room = get_form_var('room', 'int');
$debug_flag = get_form_var('debug_flag', 'int');

// 3-value compare: Returns result of compare as "< " "= " or "> ".
function cmp3($a, $b)
{
  if ($a < $b)
  {
    return "< ";
  }
  if ($a == $b)
  {
    return "= ";
  }
  return "> ";
}

// Default parameters:
if (empty($debug_flag))
{
  $debug_flag = 0;
}
if (empty($month) || empty($year) ||
    !checkdate($month, 1, $year))
{
  $month = date("m");
  $year  = date("Y");
}

$day = 1;

// print the page header
print_header($day, $month, $year, $area);

if (empty($area))
{
  $area = get_default_area();
}
if (empty($room))
{
  $room = get_default_room($area);
}
// Note $room will be 0 if there are no rooms; this is checked for below.

// Month view start time. This ignores morningstarts/eveningends because it
// doesn't make sense to not show all entries for the day, and it messes
// things up when entries cross midnight.
$month_start = mktime(0, 0, 0, $month, 1, $year);

// What column the month starts in: 0 means $weekstarts weekday.
$weekday_start = (date("w", $month_start) - $weekstarts + 7) % 7;

$days_in_month = date("t", $month_start);

$month_end = mktime(23, 59, 59, $month, $days_in_month, $year);

if ( $enable_periods )
{
  $resolution = 60;
  $morningstarts = 12;
  $eveningends = 12;
  $eveningends_minutes = count($periods)-1;
}


// Define the start and end of each day of the month in a way which is not
// affected by daylight saving...
for ($j = 1; $j<=$days_in_month; $j++)
{
  // are we entering or leaving daylight saving
  // dst_change:
  // -1 => no change
  //  0 => entering DST
  //  1 => leaving DST
  $dst_change[$j] = is_dst($month,$j,$year);
  if (empty( $enable_periods ))
  {
    $midnight[$j]=mktime(0,0,0,$month,$j,$year, is_dst($month,$j,$year, 0));
    $midnight_tonight[$j]=mktime(23,59,59,$month,$j,$year, is_dst($month,$j,$year, 23));
  }
  else
  {
    $midnight[$j]=mktime(12,0,0,$month,$j,$year, is_dst($month,$j,$year, 0));
    $midnight_tonight[$j]=mktime(12,count($periods),59,$month,$j,$year, is_dst($month,$j,$year, 23));
  }
}

// Section with areas, rooms, minicals.
?>
<div class="screenonly">
  <div id="dwm_header">
<?php

$this_area_name = "";
$this_room_name = "";

// Show all areas
echo "<div id=\"dwm_areas\"><h3>".get_vocab("areas")."</h3>";

// show either a select box or the normal html list
if ($area_list_format == "select")
{
  echo make_area_select_html('month.php', $area, $year, $month, $day);
  $this_area_name = sql_query1("select area_name from $tbl_area
                                where id=$area");
  $this_room_name = sql_query1("select room_name from $tbl_room
                                where id=$room");
}
else
{
  $sql = "select id, area_name from $tbl_area order by area_name";
  $res = sql_query($sql);
  if ($res)
  {
    echo "<ul>\n";
    for ($i = 0; ($row = sql_row_keyed($res, $i)); $i++)
    {
      echo "<li><a href=\"month.php?year=$year&amp;month=$month&amp;area=$row[0]\">";
      echo "<span";
      if ($row['id'] == $area)
      {
        $this_area_name = htmlspecialchars($row['area_name']);
        echo ' class="current"';
      }
      echo ">";
      echo htmlspecialchars($row['area_name']) . "</span></a></li>\n";
    }
    echo "</ul>\n";
  }
} // end select if

echo "</div>\n";
    
// Show all rooms in the current area:
echo "<div id=\"dwm_rooms\"><h3>".get_vocab("rooms")."</h3>";

// should we show a drop-down for the room list, or not?
if ($area_list_format == "select")
{
  echo make_room_select_html('month.php', $area, $room, $year, $month, $day);
}
else
{
  $sql = "select id, room_name from $tbl_room
          where area_id=$area order by room_name";
  $res = sql_query($sql);
  if ($res)
  {
    echo "<ul>\n";
    for ($i = 0; ($row = sql_row_keyed($res, $i)); $i++)
    {
      echo "<li><a href=\"month.php?year=$year&amp;month=$month&amp;area=$area&amp;room=".$row['id']."\">";
      echo "<span";
      if ($row['id'] == $room)
      {
        $this_room_name = htmlspecialchars($row['room_name']);
        echo ' class="current"';
      }
      echo ">";
      echo htmlspecialchars($row['room_name']) . "</span></a></li>\n";
    }
    echo "</ul>\n";
  }
} // end select if

echo "</div>\n";
    
// Draw the three month calendars
minicals($year, $month, $day, $area, $room, 'month');
echo "</div>\n";

// End of "screenonly" div
echo "</div>\n";

// Don't continue if this area has no rooms:
if ($room <= 0)
{
  echo "<h1>".get_vocab("no_rooms_for_area")."</h1>";
  include "trailer.inc";
  exit;
}

// Show Month, Year, Area, Room header:
echo "<h2 id=\"dwm\">" . utf8_strftime("%B %Y", $month_start)
  . " - $this_area_name - $this_room_name</h2>\n";

// Show Go to month before and after links
//y? are year and month of the previous month.
//t? are year and month of the next month.

$i= mktime(12,0,0,$month-1,1,$year);
$yy = date("Y",$i);
$ym = date("n",$i);

$i= mktime(12,0,0,$month+1,1,$year);
$ty = date("Y",$i);
$tm = date("n",$i);

$before_after_links_html = "<div class=\"screenonly\">
  <div class=\"date_nav\">
    <div class=\"date_before\">
      <a href=\"month.php?year=$yy&amp;month=$ym&amp;area=$area&amp;room=$room\">
          &lt;&lt;&nbsp;".get_vocab("monthbefore")."
        </a>
    </div>
    <div class=\"date_now\">
      <a href=\"month.php?area=$area&amp;room=$room\">
          ".get_vocab("gotothismonth")."
        </a>
    </div>
    <div class=\"date_after\">
       <a href=\"month.php?year=$ty&amp;month=$tm&amp;area=$area&amp;room=$room\">
          ".get_vocab("monthafter")."&nbsp;&gt;&gt;
        </a>
    </div>
  </div>
</div>
";

print $before_after_links_html;

if ($debug_flag)
{
  echo "<p>DEBUG: month=$month year=$year start=$weekday_start range=$month_start:$month_end</p>\n";
}

// Used below: localized "all day" text but with non-breaking spaces:
$all_day = ereg_replace(" ", "&nbsp;", get_vocab("all_day"));

//Get all meetings for this month in the room that we care about
// row[0] = Start time
// row[1] = End time
// row[2] = Entry ID
// This data will be retrieved day-by-day fo the whole month
for ($day_num = 1; $day_num<=$days_in_month; $day_num++)
{
  $sql = "SELECT start_time, end_time, id, name, type
          FROM $tbl_entry
          WHERE room_id=$room
          AND start_time <= $midnight_tonight[$day_num] AND end_time > $midnight[$day_num]
          ORDER by 1";

  // Build an array of information about each day in the month.
  // The information is stored as:
  //  d[monthday]["id"][] = ID of each entry, for linking.
  //  d[monthday]["data"][] = "start-stop" times or "name" of each entry.

  $res = sql_query($sql);
  if (! $res)
  {
    echo sql_error();
  }
  else
  {
    for ($i = 0; ($row = sql_row_keyed($res, $i)); $i++)
    {
      if ($debug_flag)
      {
        echo "<br>DEBUG: result $i, id ".$row['id'].", starts ".$row['start_time'].", ends ".$row['end_time']."\n";
      }

      if ($debug_flag)
      {
        echo "<br>DEBUG: Entry ".$row['id']." day $day_num\n";
      }
      $d[$day_num]["id"][] = $row['id'];
      $d[$day_num]["shortdescrip"][] = htmlspecialchars($row['name']);
      $d[$day_num]["color"][] = $row['type'];

      // Describe the start and end time, accounting for "all day"
      // and for entries starting before/ending after today.
      // There are 9 cases, for start time < = or > midnight this morning,
      // and end time < = or > midnight tonight.
      // Use ~ (not -) to separate the start and stop times, because MSIE
      // will incorrectly line break after a -.
      
      if (empty( $enable_periods ) )
      {
        switch (cmp3($row['start_time'], $midnight[$day_num]) . cmp3($row['end_time'], $midnight_tonight[$day_num] + 1))
        {
          case "> < ":         // Starts after midnight, ends before midnight
          case "= < ":         // Starts at midnight, ends before midnight
            $d[$day_num]["data"][] = htmlspecialchars(utf8_strftime(hour_min_format(), $row['start_time'])) . "~" . htmlspecialchars(utf8_strftime(hour_min_format(), $row['end_time']));
            break;
          case "> = ":         // Starts after midnight, ends at midnight
            $d[$day_num]["data"][] = htmlspecialchars(utf8_strftime(hour_min_format(), $row['start_time'])) . "~24:00";
            break;
          case "> > ":         // Starts after midnight, continues tomorrow
            $d[$day_num]["data"][] = htmlspecialchars(utf8_strftime(hour_min_format(), $row['start_time'])) . "~====&gt;";
            break;
          case "= = ":         // Starts at midnight, ends at midnight
            $d[$day_num]["data"][] = $all_day;
            break;
          case "= > ":         // Starts at midnight, continues tomorrow
            $d[$day_num]["data"][] = $all_day . "====&gt;";
            break;
          case "< < ":         // Starts before today, ends before midnight
            $d[$day_num]["data"][] = "&lt;====~" . htmlspecialchars(utf8_strftime(hour_min_format(), $row['end_time']));
            break;
          case "< = ":         // Starts before today, ends at midnight
            $d[$day_num]["data"][] = "&lt;====" . $all_day;
            break;
          case "< > ":         // Starts before today, continues tomorrow
            $d[$day_num]["data"][] = "&lt;====" . $all_day . "====&gt;";
            break;
        }
      }
      else
      {
        $start_str = period_time_string($row['start_time']);
        $end_str   = period_time_string($row['end_time'], -1);
        switch (cmp3($row['start_time'], $midnight[$day_num]) . cmp3($row['end_time'], $midnight_tonight[$day_num] + 1))
        {
          case "> < ":         // Starts after midnight, ends before midnight
          case "= < ":         // Starts at midnight, ends before midnight
            $d[$day_num]["data"][] = $start_str . "~" . $end_str;
            break;
          case "> = ":         // Starts after midnight, ends at midnight
            $d[$day_num]["data"][] = $start_str . "~24:00";
            break;
          case "> > ":         // Starts after midnight, continues tomorrow
            $d[$day_num]["data"][] = $start_str . "~====&gt;";
            break;
          case "= = ":         // Starts at midnight, ends at midnight
            $d[$day_num]["data"][] = $all_day;
            break;
          case "= > ":         // Starts at midnight, continues tomorrow
            $d[$day_num]["data"][] = $all_day . "====&gt;";
            break;
          case "< < ":         // Starts before today, ends before midnight
            $d[$day_num]["data"][] = "&lt;====~" . $end_str;
            break;
          case "< = ":         // Starts before today, ends at midnight
            $d[$day_num]["data"][] = "&lt;====" . $all_day;
            break;
          case "< > ":         // Starts before today, continues tomorrow
            $d[$day_num]["data"][] = "&lt;====" . $all_day . "====&gt;";
            break;
        }
      }
    }
  }
}
if ($debug_flag)
{
  echo "<p>DEBUG: Array of month day data:</p><pre>\n";
  for ($i = 1; $i <= $days_in_month; $i++)
  {
    if (isset($d[$i]["id"]))
    {
      $n = count($d[$i]["id"]);
      echo "Day $i has $n entries:\n";
      for ($j = 0; $j < $n; $j++)
      {
        echo "  ID: " . $d[$i]["id"][$j] .
          " Data: " . $d[$i]["data"][$j] . "\n";
      }
    }
  }
  echo "</pre>\n";
}

// Include the active cell content management routines. 
// Must be included before the beginnning of the main table.
if ($javascript_cursor) // If authorized in config.inc.php, include the javascript cursor management.
{
  echo "<script type=\"text/javascript\" src=\"xbLib.js\"></script>\n";
  echo "<script type=\"text/javascript\">\n";
  echo "//<![CDATA[\n";
  echo "InitActiveCell("
    . ($show_plus_link ? "true" : "false") . ", "
    . "false, "
    . "false, "
    . "\"$highlight_method\", "
    . "\"" . get_vocab("click_to_reserve") . "\""
    . ");\n";
  echo "//]]>\n";
  echo "</script>\n";
}

echo "<table class=\"dwm_main\" id=\"month_main\">\n<tr>";
// Weekday name header row:
for ($weekcol = 0; $weekcol < 7; $weekcol++)
{
  echo "<th>" . day_name(($weekcol + $weekstarts)%7) . "</th>";
}
echo "</tr><tr>\n";

// Skip days in week before start of month:
for ($weekcol = 0; $weekcol < $weekday_start; $weekcol++)
{
  echo "<td class=\"invalid\"><div class=\"cell_container\">&nbsp;</div></td>\n";
}

// Draw the days of the month:
for ($cday = 1; $cday <= $days_in_month; $cday++)
{
  if ($weekcol == 0)
  {
    echo "</tr><tr>\n";
  }
  echo "<td class=\"valid\">\n";
  echo "<div class=\"cell_container\">\n";
  
  echo "<div class=\"cell_header\">\n";
  // first put in the day of the month
  echo "<a class=\"monthday\" href=\"day.php?year=$year&amp;month=$month&amp;day=$cday&amp;area=$area\">$cday</a>\n";
  
  // then the link to make a new booking
  if ($javascript_cursor)
  {
    echo "<script type=\"text/javascript\">\n";
    echo "//<![CDATA[\n";
    echo "BeginActiveCell();\n";
    echo "//]]>\n";
    echo "</script>\n";
  }
  if ($enable_periods)
  {
    echo "<a class=\"new_booking\" href=\"edit_entry.php?room=$room&amp;area=$area&amp;period=0&amp;year=$year&amp;month=$month&amp;day=$cday\">\n";
    echo "<img src=\"new.gif\" alt=\"New\" width=\"10\" height=\"10\">\n";
    echo "</a>\n";
  }
  else
  {
    echo "<a class=\"new_booking\" href=\"edit_entry.php?room=$room&amp;area=$area&amp;hour=$morningstarts&amp;minute=0&amp;year=$year&amp;month=$month&amp;day=$cday\">\n";
    echo "<img src=\"new.gif\" alt=\"New\" width=\"10\" height=\"10\">\n";
    echo "</a>\n";
  }
  if ($javascript_cursor)
  {
    echo "<script type=\"text/javascript\">\n";
    echo "//<![CDATA[\n";
    echo "EndActiveCell();\n";
    echo "//]]>\n";
    echo "</script>\n";
  }
  echo "</div>\n";
  
  // then any bookings for the day
  if (isset($d[$cday]["id"][0]))
  {
    echo "<div class=\"booking_list\">\n";
    $n = count($d[$cday]["id"]);
    // Show the start/stop times, 1 or 2 per line, linked to view_entry.
    for ($i = 0; $i < $n; $i++)
    {
      // give the enclosing div the appropriate width: full width if both,
      // otherwise half-width (but use 49.9% to avoid rounding problems in some browsers)
      echo "<div class=\"" . $d[$cday]["color"][$i] . "\"" .
        " style=\"width: " . (($monthly_view_entries_details == "both") ? '100%' : '49.9%') . "\">\n"; 
      switch ($monthly_view_entries_details)
      {
        case "description":
        {
          echo "<a href=\"view_entry.php?id=" . $d[$cday]["id"][$i]
            . "&amp;day=$cday&amp;month=$month&amp;year=$year\" title=\""
            . $d[$cday]["data"][$i] . "\">"
            . utf8_substr($d[$cday]["shortdescrip"][$i], 0, 255)
            . "</a>\n";
          break;
        }
        case "slot":
        {
          echo "<a href=\"view_entry.php?id=" . $d[$cday]["id"][$i]
            . "&amp;day=$cday&amp;month=$month&amp;year=$year\" title=\""
            . utf8_substr($d[$cday]["shortdescrip"][$i], 0, 255) . "\">"
            . $d[$cday]["data"][$i] . "</a>\n";
          break;
        }
        case "both":
        {
          echo "<a href=\"view_entry.php?id=" . $d[$cday]["id"][$i]
            . "&amp;day=$cday&amp;month=$month&amp;year=$year\">"
            . $d[$cday]["data"][$i] . " "
            . utf8_substr($d[$cday]["shortdescrip"][$i], 0, 255) . "</a>\n";
          break;
        }
        default:
        {
          echo "error: unknown parameter";
        }
      }
      echo "</div>\n";
    }
    echo "</div>\n";
  }
  
  echo "</div>\n";
  echo "</td>\n";
  if (++$weekcol == 7)
  {
    $weekcol = 0;
  }
}

// Skip from end of month to end of week:
if ($weekcol > 0)
{
 for (; $weekcol < 7; $weekcol++)
 {
   echo "<td class=\"invalid\"><div class=\"cell_container\">&nbsp;</div></td>\n";
 }
}
echo "</tr></table>\n";

print $before_after_links_html;
show_colour_key();

include "trailer.inc";
?>
