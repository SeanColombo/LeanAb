<?php
/**
 * @author: Sean Colombo
 * @date: 20161130
 *
 * An extremely lightweight example of a reporting page using LeanAb, which will show
 * every experiment that's been done.  The code here can be copy/pasted to just about
 * any page system you have. If you don't want the results of your experiments to be
 * public, remember to password-protect the page where you display the reports.
 */

include_once 'LeanAb.php';

?><html>
	<head>
		<style>
		table{ border-collapse:collapse; }
		td,th{ border:1px solid black; }
		</style>
	</head>
	<body>
		<h1>Example reporting page for Lean A/B Testing Framework</h1>

		<?php
		$experimentName = (isset($_GET['report']) ? $_GET['report'] : "");
		if(!empty($experimentName)){
			print "<h2>Report for experiment: ".htmlentities($experimentName)."</h2><br/>\n";
			LeanAb::printReport( $experimentName );
		}
		?>

		<br/><br/>
		<h2>View Reports for any experiment</h2>
		<p>Here is a list of all experiments!  Click the experiment name to view a report for the experiment.</p>
		<ul><?php
			$names = LeanAb::getAllExperimentNames();
			foreach($names as $name){
				print "<li><a href='".$_SERVER['PHP_SELF']."?report=".urlencode($name)."'>".htmlentities($name)."</a></li>\n";
			}
			if(!empty($experimentName)){
				print "<li><a href='".$_SERVER['PHP_SELF']."'>...Don't show a report.</a></li>\n";
			}
		?></ul>
	</body>
</html>
