<?php
// Connect to database
function connect()
{
	$conn = mysqli_connect("host", "login", "password", "database");
	mysqli_query($conn, "SET NAMES 'utf8' COLLATE 'utf8_general_ci'");
	mysqli_query($conn, "SET CHARACTER SET 'utf8'");
	// Check connection
	if (!$conn) {
		die("Connection failed: " . mysqli_connect_error());
	}
	return $conn;
}

function inputSearch()
{
	$conn = connect();
	$request = trim(file_get_contents("php://input"));
	$request = json_decode($request, true);
	$request = $request['request'];

	// Returning array
	$out = [];
	$out['results'] = [];
	// Strict matches (category name does not contain all search words, there are no special words)
	$strogResults = [];
	// Similar matches (Matches in keys without exceptions)
	$similarResults = [];
	// Other results (Different matches, including exceptions)
	$anotherResults = [];

	// Search text
	$text = mysqli_real_escape_string($conn, $request);
	$out['text'] = htmlspecialchars($text);

	// Array from words of request (to compare them independently)
	$requestArr = preg_split("/[\W]/iu", mb_strtolower($text), null, PREG_SPLIT_NO_EMPTY);
	//finish if nothing to search
	if(empty($requestArr)) {
		echo json_encode($out);
		return;
	}

	$requestArrLen = sizeof($requestArr); // Search text array length


	// Words that do not carry a semantic load for search or excluded on purpose
	$exceptions = array("and", "or", "of", "a", "the", "some", "any");

	$sql = "SELECT `id`, `city`, `iso3`, `keywords` FROM `cities`";
	$res = mysqli_query($conn, $sql);

	// The number of results
	$srchResults = 0;

	while ($row = mysqli_fetch_assoc($res)) {
		if ($srchResults < 10) {
			/* 
			Keywords column - contains words from target column separated by "+" without exceptions(if required), 
			and may contains extra words(if required)
			A function for creating a keyword column are provided by me in createKeywords.php
			*/ 
			
			$srchArr = preg_split("/\+/", $row['keywords'], null, PREG_SPLIT_NO_EMPTY); // Array of keywords
			unset($row['keywords']);
			// Array of words in searched string (it can be different from keywords column, which are without exceptions and may have extra words)
			$nameArr = preg_split("/[\W]/iu", mb_strtolower($row['city']), null, PREG_SPLIT_NO_EMPTY);
			// Number of any key and query matches
			$matchesNumb = 0;
			
			$fullMatches = []; // Array of complete matches of the query word with the key
			$stepMatches = []; // Array of sequential match (beginning of word)
			$roughMatches = []; // Approximate matches

			// Comparing each word of request with each of keywords to sort coincidences by relevance
			foreach ($requestArr as $a) {
				$aLen = mb_strlen($a);
				foreach ($srchArr as $jKey => $j) {
					$jLen = mb_strlen($j);
					if ($a == $j) {
						$fullMatches[] = $j;
						$relKeys[$a] = '1';

						unset($srchArr[$jKey]);
						$matchesNumb++;
						break;
					} else if ($aLen < $jLen) {
						if (mb_substr($j, 0, $aLen) == $a) {
							if ($aLen >= 4) {
								$fullMatches[] = $j;
								$relKeys[$a] = '2';
								unset($srchArr[$jKey]);
							} else if ($aLen >= 3 && ($jLen - $aLen) <= 2) {
								$fullMatches[] = $j;
								$relKeys[$a] = '2';
								unset($srchArr[$jKey]);
							} else {
								$stepMatches[] = $j;
							}
							$matchesNumb++;
							break;
						} else if (($aLen + 1) == $jLen) {
							if ($aLen >= 4 && $aLen <= 6) {
								if (levenshtein($a, $j) <= 1) {
									$roughMatches[] = $j;
									$relKeys[$j] = '3';

									unset($srchArr[$jKey]);
									$matchesNumb++;
									break;
								}
							} else if ($aLen > 6) {
								if (levenshtein($a, $j) <= 2) {
									$roughMatches[] = $j;
									$relKeys[$j] = '3';

									unset($srchArr[$jKey]);
									$matchesNumb++;
									break;
								}
							}
						}
					} else if (($aLen - 1) == $jLen) {
						if ($aLen >= 4 && $aLen <= 6) {
							if (levenshtein($a, $j) <= 1) {
								$roughMatches[] = $j;
								$relKeys[$j] = '3';

								unset($srchArr[$jKey]);
								$matchesNumb++;
								break;
							}
						} else if ($aLen > 6) {
							if (levenshtein($a, $j) <= 2) {
								$roughMatches[] = $j;
								$relKeys[$j] = '3';

								unset($srchArr[$jKey]);
								$matchesNumb++;
								break;
							}
						}
					} else if ($aLen == $jLen) {
						if ($aLen >= 4 && $aLen <= 6) {
							if (levenshtein($a, $j) < 2) {
								$roughMatches[] = $j;
								$relKeys[$j] = '3';

								unset($srchArr[$jKey]);
								$matchesNumb++;
								break;
							}
						} else if ($aLen > 6) {
							if (levenshtein($a, $j) <= 2) {
								$roughMatches[] = $j;
								$relKeys[$j] = '3';

								unset($srchArr[$jKey]);
								$matchesNumb++;
								break;
							}
						}
					} else {
						continue;
					}
				}
			}

			// NUMBER of matches EQUAL to query array length (Best match)
			if ($matchesNumb == $requestArrLen) {
				// All coincidences are full
				if (!empty($fullMatches) && empty($stepMatches) && empty($roughMatches)) {
					// Full coincidences with target column
					$nameFullMatches = array_intersect($fullMatches, $nameArr);
					// Full coincidences without exceptions
					$nonExceptionsMatches = array_diff($fullMatches, $exceptions);

					// If all coincidences are full and in target column
					if ($matchesNumb == sizeof($nameFullMatches)) {
						// If the number of words in the name = the number of words in the request
						if ($matchesNumb == sizeof($nameArr)) {
							$out['results'][] = $row;
							$srchResults++;
						}
						else {
							$strogResults[] = $row;
							$srchResults++;
						}
					}
					// If all coincidences are full, but not all of them in target column
					else if ($nameFullMatches) {
						// All coincidences are in target column without exceptions
						if (array_intersect($nonExceptionsMatches, $nameFullMatches)) {
							$strogResults[] = $row;
							$srchResults++;
						}
						// All coincidences in target column are exceptions only
						else {
							$similarResults[] = $row;
						}
					}
					// No coincidences in target column
					else if (!$nameFullMatches) {
						if ($nonExceptionsMatches) {
							$similarResults[] = $row;
						}
						else {
							$anotherResults[] = $row;
						}
					}

				}
				// All coincidences are full or similar (rough)
				else if (empty($stepMatches) && !empty($fullMatches) && !empty($roughMatches)) {
					// Full and rough matches
					$fullRoughMatches = array_merge($fullMatches, $roughMatches);
					// Full or rough matches with target column
					$nameFullRoughMatches = array_intersect($fullRoughMatches, $nameArr);
					// All full or rough coincidences without exceptions
					$nonExceptionsMatches = array_diff($fullRoughMatches, $exceptions);

					// If all coincidences are full or rough AND are in target column
					if ($matchesNumb == sizeof($nameFullRoughMatches)) {
						// Number or words in target column = number or words in request
						if ($matchesNumb == sizeof($nameArr)) {
							$strogResults[] = $row;
							$srchResults++;
						}
						else {
							$similarResults[] = $row;
						}
					}
					// If all coincidences are full or rough, but not all of them in target column
					else if ($nameFullRoughMatches) {
						// If are coincidences in target column without exceptions 
						if (array_intersect($nonExceptionsMatches, array_intersect($fullMatches, $nameArr))) {
							$strogResults[] = $row;
							$srchResults++;
						}
						// If coincidences with target column are rough or exceptions
						else {
							$anotherResults[] = $row;
						}
					}
					// No coincidences in target column
					else if (!$nameFullRoughMatches) {
						if ($nonExceptionsMatches) {
							$similarResults[] = $row;
						} else {
							$anotherResults[] = $row;
						}
					}
				}

				// Coincidences are full or step (consecutive)
				else if (empty($roughMatches) && !empty($fullMatches) && !empty($stepMatches)) {
					
					$nameStepMatches = array_intersect($stepMatches, $nameArr); // Step coincidences in target column
					$fullStepMatches = array_merge($fullMatches, $stepMatches);// Full and step coincidences
					$nameFullStepMatches = array_intersect($fullStepMatches, $nameArr); // Full and step coincidences in target column

					// There is one consecutive match in the name, the rest are full 
					if (sizeof($nameStepMatches) == 1 && sizeof($nameFullStepMatches) > 1) {
						if ($matchesNumb == sizeof($nameArr)) {
							$strogResults[] = $row;
							$srchResults++;
						}
						else {
							$similarResults[] = $row;
						}
					}
					// There is at least one full coincidence in target column
					else if (array_intersect($fullMatches, $nameArr)) {
						$similarResults[] = $row;
					}
					else {
						$anotherResults[] = $row;
					}
				}

				// Coincidences are rough or step (consecutive)
				else if (empty($fullMatches) && !empty($roughMatches) && !empty($stepMatches)) {
					
					$nameStepMatches = array_intersect($stepMatches, $nameArr); // Step coincidences in target column
					$roughStepMatches = array_merge($roughMatches, $stepMatches); //Rough and step coincidences
					$nameRoughStepMatches = array_intersect($roughStepMatches, $nameArr); //Rough and step coincidences in target column

					// At least one step coincidence in target column, the rest are rough
					if (sizeof($nameStepMatches) == 1 && sizeof($nameRoughStepMatches) > 1) {
						if ($matchesNumb == sizeof($nameArr)) {
							$strogResults[] = $row;
							$srchResults++;
						} else {
							$similarResults[] = $row;
						}
					}
					// At least one step coincidence in target column
					else if (array_intersect($roughMatches, $nameArr)) {
						$similarResults[] = $row;
					}
					else {
						$anotherResults[] = $row;
					}
				}
				// Another coincidences
				else {
					// Step coincidences with target column
					$nameStepMatches = array_intersect($stepMatches, $nameArr);

					// If there is one step coincidence in target column and one word in request
					if ($requestArrLen == 1 && $matchesNumb == sizeof($nameStepMatches)) {
						if ($matchesNumb == sizeof($nameArr)) {
							$out['results'][] = $row;
							$srchResults++;
						}
						else {
							$strogResults[] = $row;
							$srchResults++;
						}
					}
					else {
						$anotherResults[] = $row;
					}
				}
			}

		} 
		else { // If 10 results are found, end the search
			break;
		}
	}
	// If there are not enough results, add another coincidences
	if (sizeof($out['results']) < 10) {
		if (!empty($strogResults)) {
			$rest = 10 - sizeof($out['results']);

			if ($rest < (sizeof($strogResults))) {
				$strogResults = array_slice($strogResults, 0, $rest);
				foreach ($strogResults as $k => $val) {
					$out['results'][] = $val;
				}
			}
			else {
				foreach ($strogResults as $k => $val) {
					$out['results'][] = $val;
				}
			}
		}

		if (sizeof($out['results']) < 10) {
			if (!empty($similarResults)) {
				$rest = 10 - sizeof($out['results']);

				if ($rest < (sizeof($similarResults))) {
					$similarResults = array_slice($similarResults, 0, $rest);
					foreach ($similarResults as $k => $val) {
						$out['results'][] = $val;
					}
				}
				else {
					foreach ($similarResults as $k => $val) {
						$out['results'][] = $val;
					}
				}
			}

			if (sizeof($out['results']) < 10) {
				if (!empty($anotherResults)) {
					$rest = 10 - sizeof($out['results']);

					if ($rest < (sizeof($anotherResults))) {
						$anotherResults = array_slice($anotherResults, 0, $rest);
						foreach ($anotherResults as $k => $val) {
							$out['results'][] = $val;
						}
					}
					else {
						foreach ($anotherResults as $k => $val) {
							$out['results'][] = $val;
						}
					}
				}
			}
		}
	}

	// If no results, but relative keys are available
	if (empty($out['results']) && isset($relKeys)) {
		// Count the number of each type of keys
		$valNum = array_count_values($relKeys);
		$k12Sum = $valNum['1'] + $valNum['2']; //Number of full and step keys

		// LESS KEYS THAN WORDS IN REQUEST (search for relevant keys together)
		if ($requestArrLen > $k12Sum) {
			$fullStepAndReq = [];
			$num = 0;
			foreach ($relKeys as $k => $val) {
				if ($val == '1' || $val == '2') {
					$num++;
					if ($num < $k12Sum) {
						$fullStepAndReq[] = "keywords LIKE '%+" . $k . "%' AND ";
					} else {
						$fullStepAndReq[] = "keywords LIKE '%+" . $k . "%'";
					}
				}
			}
			$fullStepAndReq = implode("", $fullStepAndReq);

			$sql1 = "SELECT `id`, `city`, `iso3`, `keywords` FROM `cities` WHERE $fullStepAndReq LIMIT 10 ";
			$res1 = mysqli_query($conn, $sql1);

			if (mysqli_num_rows($res1) > 0) {
				while ($row = mysqli_fetch_assoc($res1)) {
					unset($row['keywords']);
					$out['results'][] = $row;
				}
			}
			// SEARCH FOR EACH KEY INDIVIDUALLY
			else {
				if (sizeof($relKeys) > 1) {
					$num = 0;
					$fullRoughOrReq = [];

					// Search for full and rough matches separately
					$k13Sum = $valNum['1'] + $valNum['3'];
					foreach ($relKeys as $k => $val) {
						if ($val == '1' || $val == '3') {
							if (!in_array($k, $exceptions)) {
								$num++;
								if ($num < $k13Sum) {
									$fullRoughOrReq[] = "keywords LIKE '%+" . $k . "%' OR ";
								} else {
									$fullRoughOrReq[] = "keywords LIKE '%+" . $k . "%'";
								}
							}
						}
					}

					$fullRoughOrReq = implode("", $fullRoughOrReq);

					$sql2 = "SELECT `id`, `city`, `iso3`, `keywords` FROM `cities` WHERE $fullRoughOrReq LIMIT 10 ";
					$res2 = mysqli_query($conn, $sql2);
					if (mysqli_num_rows($res2) > 0) {
						while ($row = mysqli_fetch_assoc($res2)) {
							unset($row['keywords']);
							$out['results'][] = $row;
						}
					}
					else {
						// SEARCH FOR ALL KEYS
						if (sizeof($relKeys) - $k13Sum) {
							$fullReq = [];
							$num = 0;
							foreach ($relKeys as $k => $val) {
								$num++;
								if ($num < count($relKeys)) {
									$fullReq[] = "keywords LIKE '%+" . $k . "%' OR ";
								} else {
									$fullReq[] = "keywords LIKE '%+" . $k . "%'";
								}
							}

							$fullReq = implode("", $fullReq);

							$sql3 = "SELECT `id`, `city`, `iso3`, `keywords` FROM `cities` WHERE $fullReq LIMIT 10 ";
							$res3 = mysqli_query($conn, $sql3);
							while ($row = mysqli_fetch_assoc($res3)) {
								unset($row['keywords']);
								$out['results'][] = $row;
							}
						}
					}
				}
			}
		}
		// Number of keys = words in the query (SEARCH FOR EACH KEY INDIVIDUALLY)
		else {
			$num = 0;
			$fullRoughOrReq = [];

			$k13Sum = $valNum['1'] + $valNum['3'];
			foreach ($relKeys as $k => $val) {
				if ($val == '1' || $val == '3') {
					if (!in_array($k, $exceptions)) {
						$num++;
						if ($num < $k13Sum) {
							$fullRoughOrReq[] = "keywords LIKE '%+" . $k . "%' OR ";
						} else {
							$fullRoughOrReq[] = "keywords LIKE '%+" . $k . "%'";
						}
					}
				}
			}

			$fullRoughOrReq = implode("", $fullRoughOrReq);

			$sql2 = "SELECT `id`, `city`, `iso3`, `keywords` FROM `cities` WHERE $fullRoughOrReq LIMIT 10 ";
			$res2 = mysqli_query($conn, $sql2);

			if (mysqli_num_rows($res2) > 0) {
				while ($row = mysqli_fetch_assoc($res2)) {
					unset($row['keywords']);
					$out['results'][] = $row;
				}
			} else {
				if (sizeof($relKeys) - $k13Sum) {
					$fullReq = [];
					$num = 0;
					foreach ($relKeys as $k => $val) {
						$num++;
						if ($num < count($relKeys)) {
							$fullReq[] = "keywords LIKE '%+" . $k . "%' OR ";
						} else {
							$fullReq[] = "keywords LIKE '%+" . $k . "%'";
						}
					}

					$fullReq = implode("", $fullReq);

					$sql3 = "SELECT `id`, `city`, `iso3`, `keywords` FROM `cities` WHERE $fullReq LIMIT 10 ";
					$res3 = mysqli_query($conn, $sql3);
					while ($row = mysqli_fetch_assoc($res3)) {
						unset($row['keywords']);
						$out['results'][] = $row;
					}
				}
			}
		}
	}

	// Relative keys to the final array
	$out['keys'] = [];
	if (!empty($relKeys)) {
		$out['keys'] = array_keys($relKeys);
	}

	echo json_encode($out);
	mysqli_close($conn);
}


// Load city information using id
function loadCityInfo()
{
	$conn = connect();
	$request = trim(file_get_contents("php://input"));
	$request = json_decode($request, true);
	$cityId = $request['id'];
	$sql = "SELECT `city`, `city_native`, `lat`, `lng`, `country`, `iso2`, `iso3`, `capital`, `population` 
	FROM `cities` WHERE `id` = '$cityId'";
	$res = mysqli_query($conn, $sql);
	
	$out = mysqli_fetch_assoc($res);

	echo json_encode($out);

	mysqli_close($conn);
}