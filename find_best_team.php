<?php

include ".env.php"; // for db settings

$dbconn = get_dbconn("local");

/************************************************************/

$roster = ["C", "1B", "2B", "3B", "SS", "OF", "OF", "OF", "SP", "SP", "SP", "RP"];

$max_salary_total = 13000;

$season = 2020;

$start_time = time();

/************************************************************/

foreach ($roster as $pos) {

	$mvps = reduce_list($pos); // mvps = most valuable players

	echo "\n**********************************************";
	echo "\nthe position is " . $pos;
	echo "\nthe number of $pos mvps is: " . sizeof($mvps);

	// initialize the good teams table with the list of most valuable catchers
	if ($pos == "C") {
		initialize_good_teams_table($mvps);
		continue;
	}

	// get the list of good teams so far
	$good_teams = get_good_teams();

	truncate_table("temp_table");

	$iterations = 0;

	echo "\ncalculating the temp list of good teams.....";

	$temp_list_iterations = 0;

	foreach($good_teams as $team_id => $team_vals) {
		foreach($mvps as $player_id => $player_vals) {

			$iterations++;

			// check to make sure that this player has not already been added to the team
			// necessary only for starting pitchers and catchers
			$team_so_far = explode("_", $team_id);

			if (!(in_array($player_id, $team_so_far))) {

				// the team_id is a _ delimited string of player_ids
				$query = "INSERT INTO temp_table SET team_id='" . $team_id . "_" . $player_id . "'";

				$query .= ", salary = " . ($team_vals["salary"] + $player_vals["salary"]);

				$query .= ", points = " . ($team_vals["points"] + $player_vals["points"]);

				mysqli_query($dbconn, $query);

				if (mysqli_error($dbconn)) {
					echo mysqli_error($dbconn);
					exit;
				}
			}
			$temp_list_iterations++;
		}
	}

	// How big is the temp table of good teams?
	$query = "SELECT * FROM temp_table";

	$result = mysqli_query($dbconn, $query);

	if (mysqli_error($dbconn)) {
		echo mysqli_error($dbconn);
		exit;
	}

	$num_rows = mysqli_num_rows($result);

	echo "\nthere are $num_rows good teams in the temp table.";
	echo "\nreducing the temp list of good teams...";

	/*********************************************************/
	// Reduce the new list of good teams

	$new_good_teams = reduce_list("temp_table");

	$good_teams_count = sizeof($new_good_teams);

	echo "\nthere are now $good_teams_count good teams";

	truncate_table("good_teams");

	populate_table("good_teams", $new_good_teams);
}

/************************************************************/

$elapsed_time = time() - $start_time;

echo "\nthe script took $elapsed_time seconds to run.";

show_best_team();

exit;

/************************************************************/

function get_dbconn($location) {

	if ($location == "local") {
		$db_settings = $GLOBALS["local"];
	}
	else {
		$db_settings = $GLOBALS["remote"];
	}

	$mysqli = new mysqli($db_settings["DB_HOST"], $db_settings["DB_USERNAME"], $db_settings["DB_PASSWORD"], "baseball", $db_settings["DB_PORT"]);

	if ($mysqli->connect_error) {
		echo "<p>could not connect to db.";

		die('Connect Error (' . $mysqli->connect_errno . ') '
			. $mysqli->connect_error);
	}
	else {
		echo "the db connection worked.";
	}
	return $mysqli;
}

function get_good_teams() {

	global $dbconn;

	$teams = [];

	$query = "SELECT * FROM good_teams ORDER BY salary ASC";

	$result = mysqli_query($dbconn, $query);

	if (mysqli_error($dbconn)) {
		echo mysqli_error($dbconn);
		exit;
	}

	while ($row = mysqli_fetch_assoc($result)) {

		$team_id = $row["team_id"];

		$teams[$team_id]["salary"] = $row["salary"];
		$teams[$team_id]["points"] = $row["points"];
	}

	return $teams;
}

function initialize_good_teams_table($catchers) {

	global $dbconn;

	$query = "TRUNCATE TABLE good_teams";

	mysqli_query($dbconn, $query);

	if (mysqli_error($dbconn)) {
		echo mysqli_error($dbconn);
		exit;
	}

	foreach ($catchers as $id => $vals) {

		$query = "INSERT INTO good_teams SET team_id='" . $id . "'";

		$query .= ", salary = " . $vals["salary"];

		$query .= ", points = " . $vals["points"];

		mysqli_query($dbconn, $query);

		if (mysqli_error($dbconn)) {
			echo mysqli_error($dbconn);
			exit;
		}
	}
}

function populate_table($table, $new_vals) {

	global $dbconn;

	truncate_table($table);

	foreach ($new_vals as $team_id => $vals) {

		$query = "INSERT INTO $table SET team_id='" . $team_id . "'";

		$query .= ", points = " . $vals["points"];

		$query .= ", salary = " . $vals["salary"];

		mysqli_query($dbconn, $query);

		if (mysqli_error($dbconn)) {
			echo mysqli_error($dbconn);
			exit;
		}
	}
}

function reduce_list($pos) {

	global $dbconn;
	global $season;

	$mvps = [];

	if ($pos == "temp_table") {
		$query = "SELECT team_id AS id, points, salary FROM temp_table";
	}
	else {
		$query = "SELECT player_id AS id, points, salary FROM playersXseasons";
		$query .= " WHERE pos='$pos' AND season = " . $season;
	}

	$result = mysqli_query($dbconn, $query);

	if (mysqli_error($dbconn)) {
		echo mysqli_error($dbconn);
		exit;
	}

	while ($row = mysqli_fetch_assoc($result)) {

		$salary = $row["salary"];
		$points = $row["points"];

		if ($pos == "temp_table") {
			$query = "SELECT * FROM temp_table WHERE team_id IS NOT NULL";
		}
		else {
			$query = "SELECT * FROM playersXseasons WHERE pos='$pos' AND season = " . $season;
		}

		$query .= " AND salary <= $salary AND points > $points";

		$r = mysqli_query($dbconn, $query);

		if (mysqli_error($dbconn)) {
			echo mysqli_error($dbconn);
			exit;
		}

		if ($pos == "OF" || $pos == "SP") {
			if (mysqli_num_rows($r) <= 2) {
				$id = $row["id"];
				$mvps[$id]["points"] = $row["points"];
				$mvps[$id]["salary"] = $row["salary"];
			}
		}
		else {
			if (mysqli_num_rows($r) == 0) {
				$id = $row["id"];
				$mvps[$id]["points"] = $row["points"];
				$mvps[$id]["salary"] = $row["salary"];
			}
		}
	}
	return $mvps;
}

function show_best_team() {

	global $dbconn;

	global $max_salary_total;

	global $season;

	$query = "SELECT team_id FROM good_teams WHERE salary <= $max_salary_total ORDER BY points DESC LIMIT 1";

	$result = mysqli_query($dbconn, $query);

	if (mysqli_error($dbconn)) {
		echo mysqli_error($dbconn);
		exit;
	}

	$row = mysqli_fetch_assoc($result);

	$team_id = $row["team_id"];

	$roster = explode("_", $team_id);

	$dbconn_remote = get_dbconn("remote");

	foreach ($roster as $player_id) {

		$query = "SELECT * FROM playersXseasons AS p, Players AS P WHERE p.player_id = $player_id";

		$query .= " AND p.player_id = P.player_id AND p.season = " . $season;

		$result = mysqli_query($dbconn_remote, $query);

		if (mysqli_error($dbconn_remote)) {
			echo mysqli_error($dbconn_remote);
			exit;
		}

		while ($row = mysqli_fetch_assoc($result)) {
			echo "\n" . $row["pos"] . " | " . $row["FNF"] . " | " . $row["team"] . " | " . $row["salary"] . " | " . $row["points"];
		}
	}
}

function truncate_table($table) {

	global $dbconn;

	$query = "TRUNCATE TABLE $table";

	mysqli_query($dbconn, $query);

	if (mysqli_error($dbconn)) {
		echo mysqli_error($dbconn);
		exit;
	}
}
