<?php
// $Id$

require_once "grab_globals.inc.php";
include "config.inc.php";
include "functions.inc";
include "$dbsys.inc";
include "mrbs_auth.inc";
include "mrbs_sql.inc";

// Get form variables
$day = get_form_var('day', 'int');
$month = get_form_var('month', 'int');
$year = get_form_var('year', 'int');
$area = get_form_var('area', 'int');
$create_by = get_form_var('create_by', 'string');
$name = get_form_var('name', 'string');
$rep_type = get_form_var('rep_type', 'int');
$description = get_form_var('description', 'string');
$hour = get_form_var('hour', 'int');
$minute = get_form_var('minute', 'int');
$duration = get_form_var('duration', 'int');
$dur_units = get_form_var('dur_units', 'string');
$all_day = get_form_var('all_day', 'string'); // bool, actually
$type = get_form_var('type', 'string');
$rooms = get_form_var('rooms', 'array');
$returl = get_form_var('returl', 'string');
$rep_id = get_form_var('rep_id', 'int');
$edit_type = get_form_var('edit_type', 'string');
$id = get_form_var('id', 'int');
$rep_end_day = get_form_var('rep_end_day', 'int');
$rep_end_month = get_form_var('rep_end_month', 'int');
$rep_end_year = get_form_var('rep_end_year', 'int');
$rep_id = get_form_var('rep_id', 'int');
$rep_day = get_form_var('rep_day', 'array'); // array of bools
$rep_num_weeks = get_form_var('rep_num_weeks', 'int');

// If we dont know the right date then make it up 
if (!isset($day) or !isset($month) or !isset($year))
{
  $day   = date("d");
  $month = date("m");
  $year  = date("Y");
}

if (empty($area))
{
  $area = get_default_area();
}

if (!getAuthorised(1))
{
  showAccessDenied($day, $month, $year, $area);
  exit;
}

if (!getWritable($create_by, getUserName()))
{
  showAccessDenied($day, $month, $year, $area);
  exit;
}

if ($name == '')
{
  print_header($day, $month, $year, $area);
?>
       <h1><?php echo get_vocab('invalid_booking'); ?></h1>
       <p>
         <?php echo get_vocab('must_set_description'); ?>
       </p>
   </body>
</html>
<?php
  exit;
}       

if ($rep_type  == 2 || $rep_type == 6)
{
  $got_rep_day = 0;
  for ($i = 0; $i < 7; $i++)
  {
    if ($rep_day[$i])
    {
      $got_rep_day =1;
      break;
    }
  }
  if ($got_rep_day == 0)
  {
    print_header($day, $month, $year, $area);
     ?>
       <h1><?php echo get_vocab('invalid_booking'); ?></h1>
       <p>
         <?php echo get_vocab('you_have_not_entered')." ".get_vocab("rep_rep_day"); ?>
       </p>
   </body>
</html>
<?php
    exit;
  }
}       

if (($rep_type == 6) && ($rep_num_weeks < 2))
{
  print_header($day, $month, $year, $area);
?>
       <h1><?php echo get_vocab('invalid_booking'); ?></h1>
       <p>
         <?php echo get_vocab('you_have_not_entered')." ".get_vocab("useful_n-weekly_value"); ?>
       </p>
   </body>
</html>
<?php
  exit;
}

// Support locales where ',' is used as the decimal point
$duration = preg_replace('/,/', '.', $duration);

if ( $enable_periods )
{
  $resolution = 60;
  $hour = 12;
  $minute = $period;
  $max_periods = count($periods);
  if ( $dur_units == "periods" && ($minute + $duration) > $max_periods )
  {
    $duration = (24*60*floor($duration/$max_periods)) +
      ($duration%$max_periods);
  }
  if ( $dur_units == "days" && $minute == 0 )
  {
    $dur_units = "periods";
    $duration = $max_periods + ($duration-1)*60*24;
  }
}

// Units start in seconds
$units = 1.0;

switch($dur_units)
{
  case "years":
    $units *= 52;
  case "weeks":
    $units *= 7;
  case "days":
    $units *= 24;
  case "hours":
    $units *= 60;
  case "periods":
  case "minutes":
    $units *= 60;
  case "seconds":
    break;
}

// Units are now in "$dur_units" numbers of seconds


if (isset($all_day) && ($all_day == "yes"))
{
  if ( $enable_periods )
  {
    $starttime = mktime(12, 0, 0, $month, $day, $year);
    $endtime   = mktime(12, $max_periods, 0, $month, $day, $year);
  }
  else
  {
    $starttime = mktime($morningstarts, $morningstarts_minutes, 0,
                        $month, $day  , $year,
                        is_dst($month, $day  , $year));
    $endtime   = mktime($eveningends, $eveningends_minutes, 0,
                        $month, $day, $year,
                        is_dst($month, $day, $year));
    $endtime += $resolution;                // add on the duration (in seconds) of the last slot as
                                            // $eveningends and $eveningends_minutes specify the 
                                            // beginning of the last slot
  }
}
else
{
  if (!$twentyfourhour_format)
  {
    if (isset($ampm) && ($ampm == "pm") && ($hour<12))
    {
      $hour += 12;
    }
    if (isset($ampm) && ($ampm == "am") && ($hour>11))
    {
      $hour -= 12;
    }
  }

  $starttime = mktime($hour, $minute, 0,
                      $month, $day, $year,
                      is_dst($month, $day, $year, $hour));
  $endtime   = mktime($hour, $minute, 0,
                      $month, $day, $year,
                      is_dst($month, $day, $year, $hour)) + ($units * $duration);

  // Round up the duration to the next whole resolution unit.
  // If they asked for 0 minutes, push that up to 1 resolution unit.
  $diff = $endtime - $starttime;
  if (($tmp = $diff % $resolution) != 0 || $diff == 0)
  {
    $endtime += $resolution - $tmp;
  }

  $endtime += cross_dst( $starttime, $endtime );
}

if (isset($rep_type) && ($rep_type > 0) &&
    isset($rep_end_month) && isset($rep_end_day) && isset($rep_end_year))
{
  // Get the repeat entry settings
  $rep_enddate = mktime($hour, $minute, 0,
                        $rep_end_month, $rep_end_day, $rep_end_year);
}
else
{
  $rep_type = 0;
}

if (!isset($rep_day))
{
  $rep_day = array();
}

// For weekly repeat(2), build string of weekdays to repeat on:
$rep_opt = "";
if (($rep_type == 2) || ($rep_type == 6))
{
  for ($i = 0; $i < 7; $i++)
  {
    $rep_opt .= empty($rep_day[$i]) ? "0" : "1";
  }
}

// Expand a series into a list of start times:
if ($rep_type != 0)
{
  $reps = mrbsGetRepeatEntryList($starttime,
                                 isset($rep_enddate) ? $rep_enddate : 0,
                                 $rep_type, $rep_opt, $max_rep_entrys,
                                 $rep_num_weeks);
}

// When checking for overlaps, for Edit (not New), ignore this entry and series:
$repeat_id = 0;
if (isset($id))
{
  $ignore_id = $id;
  $repeat_id = sql_query1("SELECT repeat_id FROM $tbl_entry WHERE id=$id");
  if ($repeat_id < 0)
  {
    $repeat_id = 0;
  }
}
else
{
  $ignore_id = 0;
}

// Acquire mutex to lock out others trying to book the same slot(s).
if (!sql_mutex_lock("$tbl_entry"))
{
  fatal_error(1, get_vocab("failed_to_acquire"));
}
    
// Check for any schedule conflicts in each room we're going to try and
// book in
$err = "";
foreach ( $rooms as $room_id )
{
  if ($rep_type != 0 && !empty($reps))
  {
    if(count($reps) < $max_rep_entrys)
    {
      for ($i = 0; $i < count($reps); $i++)
      {
        // calculate diff each time and correct where events
        // cross DST
        $diff = $endtime - $starttime;
        $diff += cross_dst($reps[$i], $reps[$i] + $diff);

        $tmp = mrbsCheckFree($room_id,
                             $reps[$i],
                             $reps[$i] + $diff,
                             $ignore_id,
                             $repeat_id);

        if (!empty($tmp))
        {
          $err = $err . $tmp;
        }
      }
    }
    else
    {
      $err        .= get_vocab("too_may_entrys") . "\n";
      $hide_title  = 1;
    }
  }
  else
  {
    $err .= mrbsCheckFree($room_id, $starttime, $endtime-1, $ignore_id, 0);
  }

} // end foreach rooms

if (empty($err))
{
  foreach ( $rooms as $room_id )
  {
    if ($edit_type == "series")
    {
      $new_id = mrbsCreateRepeatingEntrys($starttime,
                                          $endtime,
                                          $rep_type,
                                          $rep_enddate,
                                          $rep_opt,
                                          $room_id,
                                          $create_by,
                                          $name,
                                          $type,
                                          $description,
                                          isset($rep_num_weeks) ? $rep_num_weeks : 0);
      // Send a mail to the Administrator
      if (MAIL_ADMIN_ON_BOOKINGS or MAIL_AREA_ADMIN_ON_BOOKINGS or
          MAIL_ROOM_ADMIN_ON_BOOKINGS or MAIL_BOOKER)
      {
        include_once "functions_mail.inc";
        // Send a mail only if this a new entry, or if this is an
        // edited entry but we have to send mail on every change,
        // and if mrbsCreateRepeatingEntrys is successful
        if ( ( (isset($id) && MAIL_ADMIN_ALL) or !isset($id) ) &&
             (0 != $new_id) )
        {
          // Get room name and area name. Would be better to avoid
          // a database access just for that. Ran only if we need
          // details
          if (MAIL_DETAILS)
          {
            $sql = "SELECT r.id AS room_id, r.room_name, r.area_id, a.area_name ";
            $sql .= "FROM $tbl_room r, $tbl_area a ";
            $sql .= "WHERE r.id=$room_id AND r.area_id = a.id";
            $res = sql_query($sql);
            $row = sql_row_keyed($res, 0);
            $room_name = $row['room_name'];
            $area_name = $row['area_name'];
          }
          // If this is a modified entry then call
          // getPreviousEntryData to prepare entry comparison.
          if ( isset($id) )
          {
            $mail_previous = getPreviousEntryData($id, 1);
          }
          $result = notifyAdminOnBooking(!isset($id), $new_id);
        }
      }
    }
    else
    {
      // Mark changed entry in a series with entry_type 2:
      if ($repeat_id > 0)
      {
        $entry_type = 2;
      }
      else
      {
        $entry_type = 0;
      }

      // Create the entry:
      $new_id = mrbsCreateSingleEntry($starttime,
                                      $endtime,
                                      $entry_type,
                                      $repeat_id,
                                      $room_id,
                                      $create_by,
                                      $name,
                                      $type,
                                      $description);

      // Send a mail to the Administrator
      if (MAIL_ADMIN_ON_BOOKINGS or MAIL_AREA_ADMIN_ON_BOOKINGS or
          MAIL_ROOM_ADMIN_ON_BOOKINGS or MAIL_BOOKER)
      {
        include_once "functions_mail.inc";
        // Send a mail only if this a new entry, or if this is an
        // edited entry but we have to send mail on every change,
        // and if mrbsCreateRepeatingEntrys is successful
        if ( ( (isset($id) && MAIL_ADMIN_ALL) or !isset($id) ) && (0 != $new_id) )
        {
          // Get room name and are name. Would be better to avoid
          // a database access just for that. Ran only if we need
          // details.
          if (MAIL_DETAILS)
          {
            $sql = "SELECT r.id AS room_id, r.room_name, r.area_id, a.area_name ";
            $sql .= "FROM $tbl_room r, $tbl_area a ";
            $sql .= "WHERE r.id=$room_id AND r.area_id = a.id";
            $res = sql_query($sql);
            $row = sql_row_keyed($res, 0);
            $room_name = $row['room_name'];
            $area_name = $row['area_id'];
          }
          // If this is a modified entry then call
          // getPreviousEntryData to prepare entry comparison.
          if ( isset($id) )
          {
            $mail_previous = getPreviousEntryData($id, 0);
          }
          $result = notifyAdminOnBooking(!isset($id), $new_id);
        }
      }
    }
  } // end foreach $rooms

  // Delete the original entry
  if (isset($id))
  {
    mrbsDelEntry(getUserName(), $id, ($edit_type == "series"), 1);
  }

  sql_mutex_unlock("$tbl_entry");

  $area = mrbsGetRoomArea($room_id);
    
  // Now its all done go back to the day view
  Header("Location: day.php?year=$year&month=$month&day=$day&area=$area");
  exit;
}

// The room was not free.
sql_mutex_unlock("$tbl_entry");

if (strlen($err))
{
  print_header($day, $month, $year, $area);
    
  echo "<h2>" . get_vocab("sched_conflict") . "</h2>\n";
  if (!isset($hide_title))
  {
    echo "<p>\n";
    echo get_vocab("conflict").":\n";
    echo "</p>\n";
    echo "<ul>\n";
  }

  echo $err;
    
  if(!isset($hide_title))
  {
    echo "</ul>\n";
  }
}

echo "<p>\n";
echo "<a href=\"" . htmlspecialchars($returl) . "\">" . get_vocab("returncal") . "</a>\n";
echo "</p>\n";

include "trailer.inc";
?>
