<?php
/**
 * @author: Sean Colombo
 * @date: 20161124
 *
 * SEE https://github.com/SeanColombo/LeanAb/blob/master/README.md FOR FULL DOCUMENTATION!
 *
 * This file is a simple framework for doing AB tests in one-line in the
 * manner described by Eric Reis in this blog post:
 * http://www.startuplessonslearned.com/2008/09/one-line-split-test-or-how-to-ab-all.html
 *
 * INSTALLATION:
 * 1. To get this system to work well with your own site, there are a few methods at
 *   the top of the file.
 * 2. This file will install database tables as needed.
 * 3. There is no Step 3.
 *
 * USAGE:
 * - Drop this php file in the same directory as your other PHP code and include it with "include 'LeanAb.php';"
 * - To start a new test, do something like this:
 *	$hypothesis = setup_experiment("FancyNewDesign1.2",
 *                           array(array("control", 50),
 *                                 array("design1", 50)));
 *	if( $hypothesis == "control" ) {
 *		// do it the old way
 *	} elseif( $hypothesis == "design1" ) {
 *	   // do it the fancy new way
 *	}
 *
 * REPORTS:
 * - To get a basic report for a test, call the static printReport with the name of an
 *   experiment. For example:
 *      LeanAb::printReport( "FancyNewDesign1.2" );
 * - To do custom filtering of your own, you can pass additional parameters (any associative
 *   array) to printReport() and that will be forwarded to the LeanAb::getFunnelForUserIds() function
 *   that you implement, so you can use them however you want. For example, users may decide to do cohort-analysis
 *   by passing in a date-range and then only returning results for users that signed up in that date-range. One
 *   could also use these optional parameters to filter for only users from a specific country, or whose name starts
 *   with the letter "Q", etc..
 *   Example:
 *      LeanAb::printReport( "TestWithTwoHypothesis", array(
 *             "startDate" => "2016-01-01 00:00:00",
 *             "endDate" => "2016-11-27 00:00:00"
 *      ));
 *
 * TECHNICAL NOTES:
 * - The system will always assume that it is installed. Before it is installed, it will cause query errors,
 *   then it will test whether it is installed & do the installation if needed.
 * - To uninstall and delete all records, run this mySQL:
 *        DROP TABLE leanAb_groups;DROP TABLE leanAb_assignments;DROP TABLE leanAb_experiments;
 *
 * FUTURE FEATURES:
 * - Make it handle logged-out users. For now it just gives them all the control-group. Ideally, it should store their
 *   group in the Session or in longer-running cookies, and merge that assignment based on their user-account if/when they sign up.
 * - How do we want to handle it, if the experiment weightings have changed since the weights that are stored in the
 *   database? In theory, people could ramp-up the size of new-additions to one group or another and that would be just
 *   fine since exposed users would not change.
 */

class LeanAb {
	const TABLE_PREFIX = "leanAb_"; // all tables used by this system will start with this name. You likely will not need to modify this.
	const TABLE_EXPERIMENTS = "experiments";
	const TABLE_GROUPS = "groups"; // group names and weightings for each experiment
	const TABLE_ASSIGNMENTS = "assignments"; // which users are assigned to which experiments

////
#region CUSTOMIZE THIS SECTION TO INTEGRATE THIS LIBRARY WITH YOUR EXISTING SITE.
	/**
	 * Customize this to return a writeable mysqli database handle for the database that LeanAb should
	 * store its information in. This should be the same database that contains your user-table to make
	 * it possible to generate reports.
	 */
	public function getDbh(){
		return db_connect(); // TODO: REPLACE WITH YOUR IMPLEMENTATION
	}
	
	/**
	 * Return a unique ID for the currently logged-in user. If the user is logged-out, returns
	 * null.
	 */
	public function getUserId(){
		return (User::isLoggedIn() ? User::getLoggedInUser()->getMemberId() : null); // TODO: REPLACE WITH YOUR IMPLEMENTATION
	}
	
	/**
	 * Return an associative array whose keys are the names of a step in your user-funnel, and whose values
	 * are the number of users and a percentage of users who got to that step of the funnel, among those users
	 * whose ids were provided in the userIds array.
	 *
	 * For example, this method may get an array of 1,000 userIds and the resulting array it could
	 * return might look like this:
	 *    array(
	 *       "Registered" => "1000 (100%)",
	 *       "Downloaded" => "650 (65%)",
	 *       "Chatted" => "350 (35%)",
	 *       "Purchased" => "100 (10%)"
	 *    );
	 * It is expected that the KEYS returned should be the same, regardless of the userIds which were provided
	 * as input.
	 *
	 * The 'additionalParameters' parameter is just forwarded from the call to LeanAb::printReport(). This makes
	 * it so that each user of LeanAb can customize their implementation to allow for filtering of their reports
	 * (eg: cohort analysis). See documentation at the top of this file for more information on how this optional
	 * parameter could be used.
	 */
	private function getFunnelForUserIds( $userIds, $additionalParameters=array()){
		// TODO: REPLACE WITH YOUR IMPLEMENTATION
		// Example return-data:
		// return array(
	    //    "Registered" => "1000 (100%)",
	    //    "Downloaded" => "650 (65%)",
	    //    "Chatted" => "350 (35%)",
	    //    "Purchased" => "100 (10%)"
	    // );
		// START BurndownForTrello-SPECIFIC IMPLEMENTATION -
		$numUsers = count($userIds);
		if($numUsers > 0){
			$idString = implode( "','", $userIds);
			$connected = simpleQuery("SELECT SUM(connected) FROM ".TABLE_FUNNEL_METRICS." WHERE user_member_id IN ('$idString')");
			$setupSprint = simpleQuery("SELECT SUM(setupSprint) FROM ".TABLE_FUNNEL_METRICS." WHERE user_member_id IN ('$idString')");
			$signedUpForTrial = simpleQuery("SELECT SUM(signedUpForTrial) FROM ".TABLE_FUNNEL_METRICS." WHERE user_member_id IN ('$idString')");
			$paid = simpleQuery("SELECT SUM(paid) FROM ".TABLE_FUNNEL_METRICS." WHERE user_member_id IN ('$idString')");
			$data = array(
				"Connected" => $connected." (".round(($connected*100)/$numUsers, 2)."%)",
				"Setup Sprint" => $setupSprint . " (".round(($setupSprint*100)/$numUsers, 2)."%)",
				"Signed Up For Trial" => $signedUpForTrial . " (".round(($signedUpForTrial*100)/$numUsers, 2)."%)",
				"Paid" => $paid . " (".round(($paid*100)/$numUsers, 2)."%)"
			 );
		} else {
			$data = array(
				"Connected" => "0 (0%)",
				"Setup Sprint" => "0 (0%)",
				"Signed Up For Trial" => "0 (0%)",
				"Paid" => "0 (0%)"
			 );
		}
		return $data;
		 // END OF BurndownForTrello-SPECIFIC IMPLEMENTATION
	}
#endregion OF CUSTOMIZED FUNCTIONS FOR INTEGRATING INTO YOUR SITE.
////

	/**
	 * Returns a hypothesis-group name that the current user should be exposed to.
	 * 
	 * Groups and weightings should be an array of experiment groups where each array contains two values:
	 * The name of the experiment-group and the percentage (0-100) of users which should be put in that
	 * group. The percentages must add up to 100 and can NOT be changed after the experiment has started
	 * running (if you try to change them in the code, they will be ignored after the first run of
	 * setup_experiment).
	 *
	 * If there is ever an error, the groupAssigned will be the FIRST group listed in groupsAndWeightings,
	 * therefore it is recommended to always put the "control" group first, if there is a control group (and
	 * it is recommended to have a control group in most cases).
	 */
	public function setupExperiment( $experimentName, $groupsAndWeightings ){
		$groupAssigned = null; // default.
		
		// Ensure that the system is installed and the experiment exists in the database.
		$dbw = $this->getDbh();
		$queryString = "SELECT COUNT(*) FROM ".self::TABLE_PREFIX.self::TABLE_EXPERIMENTS." WHERE name='".LeanAb::querySafe( $experimentName )."'";
		if($result = mysqli_query($dbw, $queryString)){
			if($myRow = mysqli_fetch_row($result)){
				$experimentExists = (0 < $myRow[0]);
				if(!$experimentExists){
					$this->createExperiment( $experimentName, $groupsAndWeightings );
				}
			}

			$userId = $this->querySafe( $this->getUserId() );
			if(empty($userId)){
				
				// NOTE: FOR NOW, LOGGED-OUT USERS ARE SKIPPED AND WILL JUST GET THE FIRST GROUP (usually the 'control' group) IN THE CONFIG.

				// TODO: Get their experiment group from cookies

				// TODO: If they are not in a group yet, assign one & store it in cookies or session state (and translate it to their permanent state when they do register).

			} else {
				// Check if the currently logged-in user is part of this experiment already. If they are, return the name of the hypothesis they were exposed to before.
				$tExperiments = self::TABLE_PREFIX . self::TABLE_EXPERIMENTS;
				$tGroups = self::TABLE_PREFIX . self::TABLE_GROUPS;
				$tAssignments = self::TABLE_PREFIX . self::TABLE_ASSIGNMENTS;
				$queryString = "SELECT $tGroups.name FROM $tExperiments,$tGroups,$tAssignments WHERE $tAssignments.user_id='$userId' AND $tAssignments.experiment_id=$tExperiments.id AND $tAssignments.group_id=$tGroups.id";
				$queryString .= " AND $tExperiments.name='".$this->querySafe($experimentName)."'";
				$groupAssigned = $this->simpleQuery( $queryString );
				if(empty($groupAssigned)){
					// If the user is not part of this experiment yet, pick a hypothesis using the weightings passed in as parameters & store it in the database.
					$groupAssigned = $this->getRandomGroupForExperiment( $experimentName, $groupsAndWeightings );

					// Store the assignment.
					$experimentId = $this->simpleQuery("SELECT id FROM $tExperiments WHERE name='".$this->querySafe($experimentName)."'");
					$groupId = $this->simpleQuery("SELECT id FROM $tGroups WHERE name='".$this->querySafe($groupAssigned)."'");
					if(!empty($groupId)){
						$queryString = "INSERT INTO $tAssignments (user_id, experiment_id, group_id) VALUES ";
						$queryString .= "('$userId', '$experimentId', '$groupId')";
						$this->sendQuery( $queryString );
					}
				}
			}
		} else {
			// Ensure that the system is installed. If it was NOT installed, we can install it, then try this setupExperiment call again.
			if($this->ensureInstalled()){
				// If the system needed to be installed... (and was just installed) run this same function again, now that the database is actually ready for it.
				return $this->setupExperiment( $experimentName, $groupsAndWeightings );
			} else {
				// Query failed, and it did not appear to be due to the system not being installed yet.
				trigger_error("Unknown error while trying to find or create experiment '".htmlentities($experimentName)."'", E_USER_WARNING);
			}
		}

		// Fall-back, if no group could be assigned, default to the first group (it is recommended that that be
		// a control-group if such a group exists).
		if($groupAssigned === null){
			if((count($groupsAndWeightings) > 0) && (count($groupsAndWeightings[0]) > 0)){
				$groupAssigned = $groupsAndWeightings[0][0]; // return the name of the first group.
			}
		}
		return $groupAssigned;
	} // end setupExperiment()

	/**
	 * Returns true if the current user is already assigned to a hypothesis-group for the named
	 * experiment, false otherwise. This is exposed by belongs_to_experiment() procedural function.
	 *
	 * WARNING: Does not check the existence of experimentName (because asking about an experiment
	 * that has not been automatically-created yet, is completely valid) in those cases, the function
	 * will return false because the user is not yet assigned to an experiment which hasn't been created
	 * yet.
	 */
	public function belongsToExperiment( $experimentName ){
		$tExperiments = self::TABLE_PREFIX . self::TABLE_EXPERIMENTS;
		$tGroups = self::TABLE_PREFIX . self::TABLE_GROUPS;
		$tAssignments = self::TABLE_PREFIX . self::TABLE_ASSIGNMENTS;
		$userId = $this->querySafe( $this->getUserId() );

		// Check if the currently logged-in user is part of this experiment already.
		$queryString = "SELECT COUNT(*) FROM $tExperiments,$tGroups,$tAssignments WHERE $tAssignments.user_id='$userId' AND $tAssignments.experiment_id=$tExperiments.id AND $tAssignments.group_id=$tGroups.id";
		$queryString .= " AND $tExperiments.name='".$this->querySafe($experimentName)."'";

		// NOTE: If there are any errors in the query (because the system is not installed, or the experiment doesn't exist yet) then
		// the user is definitely not assigned to the experiment yet, so we'll return false.
		$numAssigned = $this->simpleQuery( $queryString ); // 0 or 1 if query worked, empty string if query failed. Only 1 if user is actually assigned.

		return (!empty($groupAssigned));
	} // end belongsToExperiment()

	/**
	 * Creates an experiment with the given name, and the weightings provided in the groupsAndWeightings array.
	 * Each group should be a sub-array in groupsAndWeightings where the first index is the name of the group
	 * and the second index is the weighting from 0-100 (percent) of how many users should go to that group. The
	 * percentages should total up to 100, or an error will be thrown and the experiment will not be created.
	 *
	 * Returns true on success, false on failure.
	 */
	private function createExperiment( $experimentName, $groupsAndWeightings ){
		// Verify that the experiment-groups' weights add up to 100.
		$hadError = false;
		$sumPercent = 0;
		foreach($groupsAndWeightings as $groupData){
			if(isset($groupData[1])){
				// Make sure the weighting is in the valid 0-100 range.
				if((!is_numeric($groupData[1])) || ($groupData[1] < 0)  || ($groupData[1] > 100)){
					trigger_error("Must specify a weight between 0 and 100 (inclusive) for experiment in LeanAb framework. Error was in experiment '".htmlentities($experimentName)."'", E_USER_WARNING);
					$hadError = true;
				} else {
					$sumPercent += $groupData[1];
				}
			}
		}
		if($sumPercent !== 100){
			trigger_error("Experiment weights in LeanAb framework must add up to 100. Error was in experiment '".htmlentities($experimentName)."'", E_USER_WARNING);
			$hadError = true;
		}

		// If there was no error, we'll insert the experiment, then each of the groups.
		if(!$hadError){
			$queryString = "INSERT INTO ".self::TABLE_PREFIX.self::TABLE_EXPERIMENTS." (name, createdOn) VALUES ('";
			$queryString .= $this->querySafe( $experimentName )."', UTC_TIMESTAMP())";
			if( $this->sendQuery( $queryString ) ){
				$experimentId = mysqli_insert_id( $this->getDbh() );
				foreach($groupsAndWeightings as $groupData){
					if(count($groupData) != 2){
						trigger_error("Experiment group arrays should have exactly 2 items in them: the name, and the percentage-weight from 0-100.  Error was in experiment '".htmlentities($experimentName)."'", E_USER_WARNING);
					}

					$queryString = "INSERT INTO ".self::TABLE_PREFIX.self::TABLE_GROUPS." (experiment_id, name, weight) VALUES (";
					$queryString .= "'$experimentId', '".$this->querySafe($groupData[0])."', '{$groupData[1]}')";
					if(!$this->sendQuery( $queryString )){
						$hadError = true;
					}
				}
			}
		}
		return (!$hadError);
	} // end createExperiment()

	/**
	 * Given an experiment and an array of weightings (see setupExperiment() docs for details on the structure of
	 * that array), will randomly choose a group to assign the current user to and return the name of that hypothesis
	 * group.  The odds of being assigned to any particular group will be proportionate to their weightings in the array.
	 *
	 * If the weightings are not provided (or explicitly null), then they will be looked-up from the database and
	 * any previously defined weightings found there will be used.
	 *
	 * If no group-assignment could be made for whatever reason (this indicates an error, because there will always
	 * be an assignment unless there is an error), then an empty string will be returned.
	 */
	private function getRandomGroupForExperiment( $experimentName, $groupsAndWeightings=null ){
		$groupAssignment = "";
		
		// If weightings were not provided, load them from the database.
		if(empty($groupsAndWeightings)){
			$groupsAndWeightings = $this->getGroupsForExperiment( $experimentName );
		}

		// Get a fairly random number, and use weightings to assign a group.
		$rand = mt_rand(1, 100);
		for($cnt=0; $cnt < count($groupsAndWeightings); $cnt++){
			$groupData = $groupsAndWeightings[$cnt];
			$groupSize = $groupData[1]; // what percentage of all new users should be assigned to this group.
			
			if($rand <= $groupSize){
				$groupAssignment = $groupData[0];
				break; // found an assignment... no more need to iterate
			} else {
				$rand -= $groupSize;
			}
		}
		
		// If no assignment was made, something went wrong so we throw an error w/a decent amount of info.
		if(empty($groupAssignment)){
			$err = "Was unable to assign a group to the current user for experiment '$experimentName'.  Random placement was '$rand'. Configuration was: ".print_r($groupsAndWeightings, true);
			trigger_error( htmlentities($err), E_USER_WARNING );
		}

		return $groupAssignment;
	}
	
	/**
	 * Given an experiment name, returns an array of the groups in that experiment. Each group is represented by a two-item
	 * array containing the name of the group and the weighting (from 0 to 100, inclusive) of what percentage of users
	 * should be assigned to that group.
	 */
	private function getGroupsForExperiment( $experimentName ){
		// Load the weightings from what is stored in the database, into a structure like the array that is normally provided.
		$groupsAndWeightings = array();
		$tExperiments = self::TABLE_PREFIX . self::TABLE_EXPERIMENTS;
		$tGroups = self::TABLE_PREFIX . self::TABLE_GROUPS;
		$queryString = "SELECT $tGroups.name, weight FROM $tGroups,$tExperiments WHERE $tExperiments.id=$tGroups.experiment_id AND $tExperiments.name='".$this->querySafe($experimentName)."'";
		$dbr = $this->getDbh();
		if($result = mysqli_query($dbr, $queryString)){
			if(($numRows = mysqli_num_rows($result)) && ($numRows > 0)){
				for($cnt=0; $cnt < $numRows; $cnt++){
					$name = mysqli_result($result, $cnt, "name");
					$weight = mysqli_result($result, $cnt, "weight");
					$groupsAndWeightings[] = array($name, $weight);
				}
			}
		} else {
			trigger_error( "Error with query<br/>\"<em>$queryString</em>\"<br/>Error was:<br/><strong>".mysqli_error( $dbr )."<br/><br/>\n", E_USER_WARNING );
		}
		return $groupsAndWeightings;
	}
	

	/**
	 * We will call this once a normal query has failed... this will then test whether the
	 * system's database schema has been installed. If not, this will create the tables as
	 * needed.
	 *
	 * This method will return true if it had to install, and false if there was no need
	 * to install. This will let calling-code re-try its action if it needed to install.
	 */
	private function ensureInstalled(){
		$hadToInstall = false;
		
		$dbr = $this->getDbh();
		$queryString = "SHOW TABLES LIKE '".self::TABLE_PREFIX.self::TABLE_EXPERIMENTS."'";
		if($result = mysqli_query($dbr, $queryString)){
			// If there are no rows in the result, then the table does not exist yet.
			if(mysqli_num_rows($result) == 0){
				// Create all of the tables... only continue to the next one if the creation is successful.
				// Experiments table!
				if($this->sendQuery("CREATE TABLE ".self::TABLE_PREFIX.self::TABLE_EXPERIMENTS." (
					id INT(11) NOT NULL AUTO_INCREMENT,
					name VARCHAR(255) NOT NULL,
					createdOn DATETIME,
					UNIQUE KEY(name),
					PRIMARY KEY(id)
				)")){
					// Group names and weightings for each experiment (one experiment will have multiple rows for its groups).
					//
					// WARNING: Currently, we will allow the calling-code to change the weight sizes at any time and that won't be
					// updated in the database. This allows users to easily ramp-up the code until all new users are added to one group
					// or another. This is dangerous, because people who don't understand how the system works may ramp up a hypothesis to 100% 
					// and think that the feature is fully rolled-out, when in reality some users would see the old system since they'd been assigned
					// before the weighting was changed.
					if($this->sendQuery("CREATE TABLE leanAb_groups (
						id INT(11) NOT NULL AUTO_INCREMENT,
						experiment_id INT(11) NOT NULL,
						name VARCHAR(255) NOT NULL,
						weight TINYINT(3) DEFAULT 0, # 0 to 100. The weights for all groups must sum to 100.
						FOREIGN KEY(experiment_id) REFERENCES leanAb_experiments(id)
						   ON DELETE CASCADE
						   ON UPDATE CASCADE,
						UNIQUE KEY(experiment_id, name),
						PRIMARY KEY(id)
					)")){
						// Records which users have been assigned to which experiment groups
						if($this->sendQuery("CREATE TABLE leanAb_assignments (
							user_id VARCHAR(255) NOT NULL, # we don't know the type of ID that the user's system will be (it might be a username) so we'll store it as varchar
							experiment_id INT(11) NOT NULL,
							group_id INT(11) NOT NULL,
							FOREIGN KEY(experiment_id) REFERENCES leanAb_experiments(id)
							   ON DELETE CASCADE
							   ON UPDATE CASCADE,
							UNIQUE KEY (user_id, experiment_id)
						)")){
							// Successfully installed all tables!
							$hadToInstall = true;
						}
					}
				}
			}
		}

		return $hadToInstall;
	}
	
	/**
	 * Sends a WRITE query (usually an insert/update/delete) and returns true on success false on failure.
	 * Nothing sophisticated here, just makes the code shorter by saving the need
	 * for other pieces of code to get the global connection to the db, handle errors, etc..
	 *
	 * NOTE: for WRITE queries primarily (use simpleQuery() for read-only queries).
	 *
	 * Returns true on success, false on failure.
	 */
	private function sendQuery( $queryString ){
		$dbw = $this->getDbh();
		if(!$retVal = mysqli_query( $dbw, $queryString)){
			trigger_error( "Error with query<br/>\"<em>$queryString</em>\"<br/>Error was:<br/><strong>".mysqli_error( $dbw )."<br/><br/>\n", E_USER_WARNING );
		}
		return $retVal;
	} // end sendQuery()
	
	/**
	 * Helper method that sends a "read" query and returns a result.
	 * PRECONDITIONS: The query should be a read-only query and should expect
	 * zero rows or a one-row-and-one-column result. This is not intended for other types of queries.
	 *
	 * If the result of the query is 0 rows long (but there is no error) then an empty-string will be returned.
	 */
	private function simpleQuery( $queryString ){
		$dbr = $this->getDbh();
		$retVal = "";
		if($result = mysqli_query($dbr, $queryString)){
			if(mysqli_num_rows($result) > 0){
				if($myRow = mysqli_fetch_row($result)){
					$retVal = $myRow[0];
				}
			}
		} else {
			trigger_error( "Error with query<br/>\"<em>$queryString</em>\"<br/>Error was:<br/><strong>".mysqli_error( $dbr )."<br/><br/>\n", E_USER_WARNING );
		}
		return $retVal;
	}
	
	/**
	 * Sends a mysql query and assumes that the result will only contain one column.
	 * Returns an array, if available, the array will contain one item for each row
	 * in the result. If not available, it will returne an empty array.
	 *
	 * This is designed for queries which return 0 to many rows, but only have one column of results per row.
	 * eg: 'SELECT id FROM users' would return an array of all user ids.
	 */
	private function columnQuery($queryString){
		$retVal = array();
		$db = db_connect();
		if($result = mysqli_query($db, $queryString)){
			if(($numRows = mysqli_num_rows($result)) && ($numRows > 0)){
				while($myRow = mysqli_fetch_row($result)){
					$retVal[] = $myRow[0];
				}
			}
		} else {
			trigger_error( "Error with query<br/>\"<em>$queryString</em>\"<br/>Error was:<br/><strong>".mysqli_error( $dbr )."<br/><br/>\n", E_USER_WARNING );
		}
		return $retVal;
	} // end columnQuery

	/**
	 * Given a variable (potentially provided by an untrusted user), make it safe for use inside of a query-string.
	 */
	private function querySafe( $variableToMakeSafe ){
		return mysqli_real_escape_string( $this->getDbh(), stripslashes($variableToMakeSafe));
	}

	/**
	 * See printReport() comments. This is just the non-static implementation of that
	 * same method-signature.
	 */
	private function printReport_INTERNAL( $experimentName, $additionalParams=array() ){
		$groupsAndWeightings = $this->getGroupsForExperiment( $experimentName );

		// If no groups were found, check to make sure that the experiment-name was correct. This helps find typos for experiment names that
		// would otherwise fail very strangely/quietly (eg: there would be no data, which would just make it look like our system is broken).
		if(count($groupsAndWeightings) == 0){
			$exists = (0 < $this->simpleQuery("SELECT COUNT(*) FROM ".self::TABLE_PREFIX.self::TABLE_EXPERIMENTS." WHERE name='".$this->querySafe($experimentName)."'"));
			if(!$exists){
				trigger_error("printReport: There was no experiment found with the name '$experimentName'. Please check the spelling.", E_USER_WARNING);
			}
		} else {
			$tGroups = self::TABLE_PREFIX . self::TABLE_GROUPS;
			$tAssignments = self::TABLE_PREFIX . self::TABLE_ASSIGNMENTS;

			// Fetch funnel-metrics for each group. This is done by farming out the querying to getFunnelForUserIds() which each
			// site using LeanAb will implement in a custom way.
			$funnelDataByGroup = array();
			foreach($groupsAndWeightings as $groupData){
				$groupName = $groupData[0];

				// Get the userIds of everyone assigned to this group.
				$queryString = "SELECT user_id FROM $tGroups,$tAssignments WHERE $tGroups.id=$tAssignments.group_id AND $tGroups.name='".$this->querySafe($groupName)."'";
				$userIds = $this->columnQuery( $queryString );

				$funnelData = $this->getFunnelForUserIds($userIds, $additionalParams);
				$funnelDataByGroup[ $groupName ] = $funnelData;
			}

			// Very basic table to show the funnel metrics for each hypothesis-group.
			print "<table class='leanAb'><caption>'$experimentName' Report</caption><thead><tr>\n";
				print "<th>&nbsp;</th>\n";
				$groups = array_keys( $funnelDataByGroup );
				foreach($groups as $groupName){
					print "<th>$groupName</th>";
				}
			print "</tr></thead><tbody>\n";
			if(count($funnelDataByGroup) > 0){
				$metricNames = array_keys( array_values($funnelDataByGroup)[0] );
				foreach($metricNames as $metric){
					print "<tr><td>$metric</td>";
					foreach($funnelDataByGroup as $groupName => $funnelData){
						print "<td>".(isset($funnelData[ $metric ]) ? $funnelData[ $metric ] : "??" )."</td>\n";
					}
					print "</tr>\n";
				}
			}
			print "</tbody></table>\n";
		}
	} // end printReport_INTERNAL()
	
	/**
	 * Returns a flat array of strings which are the experiment names.
	 */
	private function getAllExperimentNames_INTERNAL(){
		return $this->columnQuery("SELECT name FROM ".self::TABLE_PREFIX.self::TABLE_EXPERIMENTS." ORDER BY createdOn");
	} // end getAllExperimentNames_INTERNAL()
	
	/**
	 * Just a static wrapper for printReport_INTERNAL().
	 *
	 * Prints a very simple HTML report for the experiment with the given experimentName. If
	 * additional parameters (additionalParams) are provided, they will be forwarded to the
	 * LeanAb::getFunnelForUserIds() method, which allows each user of LeanAb to implement
	 * custom-reporting (such as filtering by signup-date, by demographic data, etc.).
	 */
	public static function printReport( $experimentName, $additionalParams=array() ){
		$leanAb = new LeanAb();
		return $leanAb->printReport_INTERNAL( $experimentName, $additionalParams );
	} // end printReport()
	
	/**
	 * Returns a list of all Experiment Names. This is very useful if you're trying to make
	 * a page which will display reports for any experiment.
	 */
	public static function getAllExperimentNames(){
		$leanAb = new LeanAb();
		return $leanAb->getAllExperimentNames_INTERNAL();
	}

} // end class LeanAb





///// PROCEDURAL METHOD BELOW - THIS IS THE MAIN INTERFACE THAT THE EXTERNAL CODE WILL BE CALLING /////

/**
 * Returns a hypothesis-group name that the current user should be exposed to.
 * 
 * Groups and weightings should be an array of experiment groups where each array contains two values:
 * The name of the experiment-group and the percentage (0-100) of users which should be put in that
 * group. The percentages must add up to 100 and can NOT be changed after the experiment has started
 * running (if you try to change them in the code, they will be ignored after the first run of
 * setup_experiment).
 */
function setup_experiment( $experimentName, $groupsAndWeightings ){
	$leanAb = new LeanAb();
	return $leanAb->setupExperiment( $experimentName, $groupsAndWeightings );
} // end setup_experiment()

/**
 * Returns true if the current user is already assigned to a hypothesis-group for the named
 * experiment, false otherwise. This is often used to look at users to potentially disqualify them from
 * participating in an experiment (ie: if user is not part of this experiment, but has already seen the
 * Boss of Level 8 of a game, then they will not be a valid experiment subject, so we'll manually treat
 * them with the Control).
 */
function belongs_to_experiment( $experimentName ){
	$leanAb = new LeanAb();
	return $leanAb->belongsToExperiment( $experimentName );
} // end belongs_to_experiment()
