<?php

// $Id$

require_once "grab_globals.inc.php";
require_once "config.inc.php";
require_once "functions.inc";
require_once "dbsys.inc";
require_once "mrbs_auth.inc";

// Get form variables
$day = get_form_var('day', 'int');
$month = get_form_var('month', 'int');
$year = get_form_var('year', 'int');
$area = get_form_var('area', 'int');
$name = get_form_var('name', 'string');
$description = get_form_var('description', 'string');
$capacity = get_form_var('capacity', 'int');
$type = get_form_var('type', 'string');

$required_level = (isset($max_level) ? $max_level : 2);
if (!getAuthorised($required_level))
{
  showAccessDenied($day, $month, $year, $area, "");
  exit();
}

// This file is for adding new areas/rooms

// we need to do different things depending on if its a room
// or an area

if ($type == "area")
{
  // Truncate the name field to the maximum length as a precaution.
  $name = substr($name, 0, $maxlength['area.area_name']);
  $area_name_q = addslashes($name);
  // Acquire a mutex to lock out others who might be editing the area
  if (!sql_mutex_lock("$tbl_area"))
  {
    fatal_error(TRUE, get_vocab("failed_to_acquire"));
  }
  // Check that the area name is unique
  if (sql_query1("SELECT COUNT(*) FROM $tbl_area WHERE area_name='$area_name_q' LIMIT 1") > 0)
  {
    $error = "invalid_area_name";
  }
  // If so, insert the area into the database
  else
  {
    $sql = "insert into $tbl_area (area_name) values ('$area_name_q')";
    if (sql_command($sql) < 0)
    {
      fatal_error(1, sql_error());
    }
    $area = sql_insert_id("$tbl_area", "id");
  }
  // Release the mutex
  sql_mutex_unlock("$tbl_area");
}

if ($type == "room")
{
  // Truncate the name and description fields to the maximum length as a precaution.
  $name = substr($name, 0, $maxlength['room.room_name']);
  $description = substr($description, 0, $maxlength['room.description']);
  // Add SQL escaping
  $room_name_q = addslashes($name);
  $description_q = addslashes($description);
  if (empty($capacity))
  {
    $capacity = 0;
  }
  // Acquire a mutex to lock out others who might be editing rooms
  if (!sql_mutex_lock("$tbl_room"))
  {
    fatal_error(TRUE, get_vocab("failed_to_acquire"));
  }
  // Check that the room name is unique within the area
  if (sql_query1("SELECT COUNT(*) FROM $tbl_room WHERE room_name='$room_name_q' AND area_id=$area LIMIT 1") > 0)
  {
    $error = "invalid_room_name";
  }
  // If so, insert the room into the datrabase
  else
  {
    $sql = "insert into $tbl_room (room_name, area_id, description, capacity)
            values ('$room_name_q',$area, '$description_q',$capacity)";
    if (sql_command($sql) < 0)
    {
      fatal_error(1, sql_error());
    }
  }
  // Release the mutex
  sql_mutex_unlock("$tbl_room");
}

$returl = "admin.php?area=$area" . (!empty($error) ? "&error=$error" : "");
header("Location: $returl");
