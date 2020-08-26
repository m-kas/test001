<?php defined('ABSPATH') || exit();

$quiz = LP_Global::course_item_quiz();
?>
<div id="content-item-quiz" class="content-item-summary single-quiz">

    <?php

    // get modal survey user id from cookie
    $userID = $_COOKIE["ms-uid-ww"];

    // get stuff from url
    $patA = "module-";
    $patB = "quizzes/";
    global $wp;
    $urlX = $wp->request;
    $modA = strpos($urlX, $patA);
    $modAA = substr($urlX, $modA + strlen($patA));
    $modAAA = strstr($modAA, "/");
    $moduleID = str_replace($modAAA, "", $modAA);
    $modB = strpos($urlX, $patB);
    $modBB = substr($urlX, $modB + strlen($patB), 1);
    $urlIDs = str_replace("-", "", strrchr($wp->request, "-"));
    $coachCommentID = str_replace('p', '', strstr($urlIDs, 'p'));
    $curentSurveyID = str_replace(strstr($urlIDs, 'p'), '', $urlIDs);

    global $wpdb;

    /* Get all my answers for this module along with survey ids */

    $getUserAnswers = $wpdb->get_results(" 
SELECT 
answers.answer, surveys.id
FROM 
" . $wpdb->prefix . "modal_survey_answers AS answers 
INNER JOIN
" . $wpdb->prefix . "modal_survey_participants_details AS details 
ON
answers.survey_id = details.sid
INNER JOIN 
" . $wpdb->prefix . "modal_survey_surveys AS surveys
ON 
details.sid = surveys.id
AND
answers.autoid = details.aid
WHERE 
details.uid='" . $userID . "' AND surveys.name='" . $moduleID . "'
");

    /* array the survey ids and array the answers */
    $surveyList = [];
    $answerList = [];

    foreach ($getUserAnswers as $obj) {
        $answerStr = $obj->answer;
        $answerStripped = strstr($answerStr, "[");
        $answerLT = str_replace("[", "", $answerStripped);
        $answerRT = str_replace("]", "", $answerLT);

        $answerValue = (int)$answerRT;
        array_push($answerList, $answerValue);

        $surveyID = $obj->id;
        if (!in_array($surveyID, $surveyList)) {
            array_push($surveyList, $surveyID);
        }
    }

    /* Calculate User Average */

    $sum = 0;
    $count = 0;
    foreach ($answerList as $value) {
        $sum += $value;
        $count++;
    }
    $average = round($sum / $count, 2);


    /* build the relevant survey list */

    // $curentSurveyID = str_replace( "-", "", strrchr($wp->request,"-") );
    if (!in_array($curentSurveyID, $surveyList)) {
        $surveyList[] = $curentSurveyID;
    }
    $surveyListString = implode("','", $surveyList);


    /* GET USER AVERAGES FOR RELEVANT SURVEY LIST (including the curent survey) */
    $getUserAverages = $wpdb->get_results(" 
SELECT 
details.uid, answers.answer
FROM 
" . $wpdb->prefix . "modal_survey_participants_details AS details 
INNER JOIN 
" . $wpdb->prefix . "modal_survey_answers AS answers
ON
answers.survey_id = details.sid
AND
answers.autoid = details.aid 
WHERE 
details.sid IN('" . $surveyListString . "') 
AND 
details.uid <> '" . $userID . "'
");

    /*  --  CALCULATE RESULT AVERAGES FOR OTHER USERS  --  */
    $usrResultArray = array();

    foreach ($getUserAverages as $userObj) {
        $curentAnswer = str_replace("[", "", strstr($userObj->answer, "["));
        $answerValue = intval(str_replace("]", "", $curentAnswer));

        if (array_key_exists($userObj->uid, $usrResultArray)) {
            array_push($usrResultArray[$userObj->uid], $answerValue);
        } else {
            $usrResultArray[$userObj->uid] = array($answerValue);
        }
    }

    $userAverages = array();
    $position = 1;
    foreach ($usrResultArray as $singleUserResults) {
        $resultSum = 0;
        $resultCount = 0;
        foreach ($singleUserResults as $result) {
            $resultSum += $result;
            $resultCount++;
        }
        $tempAverage = round($resultSum / $resultCount, 2);
        if ($tempAverage > $average) {
            $position++;
        }
        $userAverages[] = $tempAverage;
    }
    $resJSON = json_encode($userAverages);


    /*      SURVEY ID'S FOR LINK DISABLE        */
    $surveysJSON = json_encode($surveyList);


    /*      DISPLAING AVERAGE SCORE ON PAST LESSONS       */
    $counter = [];
    $stopNum = intval($modBB);
    for ($i = 1; $i <= $modBB; $i++) {
        $counter[] = strval($i);
    }
    $problemListString = implode($counter, "|");

    $getUserPastScore = $wpdb->get_results("
    SELECT 
        answers.answer
    FROM 
        " . $wpdb->prefix . "modal_survey_answers AS answers 
    INNER JOIN
        " . $wpdb->prefix . "modal_survey_participants_details AS details 
    ON
        answers.survey_id = details.sid
    INNER JOIN 
        " . $wpdb->prefix . "modal_survey_surveys AS surveys
    ON 
        details.sid = surveys.id
    AND
        answers.autoid = details.aid
    INNER JOIN
        " . $wpdb->prefix . "modal_survey_questions AS questions
    ON
        questions.survey_id = surveys.id
    WHERE 
        details.uid='" . $userID . "' 
    AND 
        surveys.name='" . $moduleID . "'
    AND
        questions.question REGEXP '^(" . $problemListString . ")'
");

    $pastScore = [];
    foreach ($getUserPastScore as $pastscore) {
        $wholeString = $pastscore->answer;
        $strippedString = strstr($wholeString, "[");
        $valueLT = str_replace("[", "", $strippedString);
        $valueRT = str_replace("]", "", $valueLT);
        $pastScore[] = $valueRT;
    };
    $pastScoreJSON = json_encode($pastScore);


    /*   GET THE USER's PAST ANSWER   */
    $getMyPastAnswer = $wpdb->get_results("
    SELECT 
        answers.answer
    FROM 
        " . $wpdb->prefix . "modal_survey_answers AS answers 
    INNER JOIN    
        " . $wpdb->prefix . "modal_survey_participants_details AS details 
    ON
        answers.survey_id = details.sid
    WHERE 
        details.uid='" . $userID . "' 
    AND
        answers.autoid = details.aid 
    AND
        answers.survey_id='" . $curentSurveyID . "'
");


    $myPastAnswers = [];
    foreach ($getMyPastAnswer as $myPastAnswer) {
        $wholeAnswer = $myPastAnswer->answer;
        $strippedAnswer = substr($wholeAnswer, 0, strpos($wholeAnswer, '['));
        $myPastAnswers[] = $strippedAnswer;
    }
    $userAnswer = json_encode($myPastAnswers);

    ?>


  <div id="wpData" class="wpData data">
      <?php
      /* DISPLAY-NONE data for DOM operations  */
      echo("<div id='u-id' class='u-id'>User id: " . $userID . "</div>");
      echo("<div id='u-answer' class='u-answer'>" . $userAnswer . "</div>");
      echo("<div id='u-sum' class='u-sum'>" . $sum . "</div>");
      echo("<div id='u-count' class='u-count'>" . $count . "</div>");
      echo "<div>curent survey id: <br>" . $curentSurveyID . "</div>";
      echo("<div id='ou-position'>");
      echo($resJSON);
      echo("</div>");
      echo("<div id='u-position' class='u-position'>Your current position is: " . $position . "<br> out of " . (count($usrResultArray) + 1) . " players.</div>");
      echo("<div id='u-surveys'>");
      echo($surveysJSON);
      echo("</div>");
      echo("<div id='u-problem'>");
      echo($modBB);
      echo("</div>");
      echo("<div id='u-past-score'>");
      echo($pastScoreJSON);
      echo("</div>");
      ?>
  </div>

    <?php
    /**
     * @see learn_press_content_item_summary_title()
     * @see learn_press_content_item_summary_content()
     */
    do_action('learn-press/before-content-item-summary/' . $quiz->get_item_type());

    ?>

    <?php
    /**
     * @see learn_press_content_item_summary_question()
     */
    do_action('learn-press/content-item-summary/' . $quiz->get_item_type());
    ?>

    <?php
    /*  ---- DISPLAY COACH COMMENT ----  */
    echo('<div id="coach-comment">');
    print_r(get_post_meta($coachCommentID, 'coach_comment', true));
    echo('</div>');
    ?>

    <?php
    /**
     * @see learn_press_content_item_summary_question_numbers()
     */
    do_action('learn-press/after-content-item-summary/' . $quiz->get_item_type());
    ?>

</div>
