# LeanAb
LeanAb is a simple library for doing AB tests in one-line in the manner described by Eric Reis in this blog post:
http://www.startuplessonslearned.com/2008/09/one-line-split-test-or-how-to-ab-all.html

The framework was built to be used by [Burndown for Trello](https://www.burndownfortrello.com/) but was made general-purpose so that any site can integrate the same functionality and only needs to implement 3 simple functions to glue LeanAb into any system.

# Installation
1. Copy the LeanAb.php file to a directory that your code can access (such as the ```/includes``` directory of your site).
2. To get this system to work well with your own site, there are a few methods at the top of the file that you need to implement to integrate with your system. Docs are provided in comments above those functions on how to implement them:
  1. LeanAb::getDbh()
  2. LeanAb::getUserId()
  3. LeanAb::getFunnelForUserIds()
3. There is no step 3.

# Usage
- Drop this php file in the same directory as your other PHP code and include it with ```include 'LeanAb.php';```
- To start a new test, do something like this:
 
```php
    $hypothesis = setup_experiment("FancyNewDesign1.2",
                            array(array("control", 50),
                                  array("design1", 50)));
	if( $hypothesis == "control" ) {
		// do it the old way
	} elseif( $hypothesis == "design1" ) {
	   // do it the fancy new way
	}
```

# Reports
- As a simple solution (or starting point) we've provided ```EXAMPLE_REPORTING_PAGE.php``` which will list all experiments and display basic reports for them. If you use the example reporting page on your site, please note that you will have to add some sort of password-protection if you do not want the results of your experiments to be public.
- To get a basic report for any test, call the static printReport with the name of an experiment. For example:
```php
     LeanAb::printReport( "FancyNewDesign1.2" );
```
- To do custom filtering of your own, you can pass additional parameters (any associative
  array) to printReport() and that will be forwarded to the LeanAb::getFunnelForUserIds() function
  that you implement, so you can use them however you want. For example, users may decide to do cohort-analysis
  by passing in a date-range and then only returning results for users that signed up in that date-range. One
  could also use these optional parameters to filter for only users from a specific country, or whose name starts
  with the letter "Q", etc..
  Example:
```php
     LeanAb::printReport( "TestWithTwoHypothesis", array(
            "startDate" => "2016-01-01 00:00:00",
            "endDate" => "2016-11-27 00:00:00"
     ));
```

# Technical Notes
- The system will always assume that it is installed. Before it is installed, it will cause query errors, then it notices those errors and it will test whether it is installed & do the installation if needed.
- To uninstall and delete all records, run this mySQL:
```mysql
DROP TABLE leanAb_groups;
DROP TABLE leanAb_assignments;
DROP TABLE leanAb_experiments;
```

# Future Features
- Make it handle logged-out users. For now it just gives them all the control-group. Ideally, it should store their group in the Session or in longer-running cookies, and merge that assignment based on their user-account if/when they sign up.
- How do we want to handle it, if the experiment weightings have changed since the weights that are stored in the database? In theory, people could ramp-up the size of new-additions to one group or another and that would be just fine since exposed users would not change.
  