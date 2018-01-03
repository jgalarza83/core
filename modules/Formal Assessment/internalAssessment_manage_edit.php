<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Forms\Form;

//Module includes
include './modules/'.$_SESSION[$guid]['module'].'/moduleFunctions.php';

//Get alternative header names
$attainmentAlternativeName = getSettingByScope($connection2, 'Markbook', 'attainmentAlternativeName');
$effortAlternativeName = getSettingByScope($connection2, 'Markbook', 'effortAlternativeName');

if (isActionAccessible($guid, $connection2, '/modules/Formal Assessment/internalAssessment_manage_edit.php') == false) {
    //Acess denied
    echo "<div class='error'>";
    echo __($guid, 'You do not have access to this action.');
    echo '</div>';
} else {
    //Check if school year specified
    $gibbonCourseClassID = $_GET['gibbonCourseClassID'];
    $gibbonInternalAssessmentColumnID = $_GET['gibbonInternalAssessmentColumnID'];
    if ($gibbonCourseClassID == '' or $gibbonInternalAssessmentColumnID == '') {
        echo "<div class='error'>";
        echo __($guid, 'You have not specified one or more required parameters.');
        echo '</div>';
    } else {
        try {
            $data = array('gibbonCourseClassID' => $gibbonCourseClassID);
            $sql = 'SELECT gibbonCourse.nameShort AS course, gibbonCourseClass.nameShort AS class, gibbonCourseClass.gibbonCourseClassID, gibbonCourse.gibbonDepartmentID, gibbonYearGroupIDList FROM gibbonCourse, gibbonCourseClass WHERE gibbonCourse.gibbonCourseID=gibbonCourseClass.gibbonCourseID AND gibbonCourseClass.gibbonCourseClassID=:gibbonCourseClassID ORDER BY course, class';
            $result = $connection2->prepare($sql);
            $result->execute($data);
        } catch (PDOException $e) {
            echo "<div class='error'>".$e->getMessage().'</div>';
        }

        if ($result->rowCount() != 1) {
            echo "<div class='error'>";
            echo __($guid, 'The selected record does not exist, or you do not have access to it.');
            echo '</div>';
        } else {
            try {
                $data2 = array('gibbonInternalAssessmentColumnID' => $gibbonInternalAssessmentColumnID);
                $sql2 = 'SELECT * FROM gibbonInternalAssessmentColumn WHERE gibbonInternalAssessmentColumnID=:gibbonInternalAssessmentColumnID';
                $result2 = $connection2->prepare($sql2);
                $result2->execute($data2);
            } catch (PDOException $e) {
                echo "<div class='error'>".$e->getMessage().'</div>';
            }

            if ($result2->rowCount() != 1) {
                echo "<div class='error'>";
                echo __($guid, 'The selected record does not exist, or you do not have access to it.');
                echo '</div>';
            } else {
                //Let's go!
                $class = $result->fetch();
                $values = $result2->fetch();

                echo "<div class='trail'>";
                echo "<div class='trailHead'><a href='".$_SESSION[$guid]['absoluteURL']."'>".__($guid, 'Home')."</a> > <a href='".$_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/'.getModuleName($_GET['q']).'/'.getModuleEntry($_GET['q'], $connection2, $guid)."'>".__($guid, getModuleName($_GET['q']))."</a> > <a href='".$_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/'.getModuleName($_GET['q']).'/internalAssessment_manage.php&gibbonCourseClassID='.$_GET['gibbonCourseClassID']."'>".__($guid, 'Manage').' '.$class['course'].'.'.$class['class'].' '.__($guid, 'Internal Assessments')."</a> > </div><div class='trailEnd'>".__($guid, 'Edit Column').'</div>';
                echo '</div>';

                if ($values['groupingID'] != '' and $values['gibbonPersonIDCreator'] != $_SESSION[$guid]['gibbonPersonID']) {
                    echo "<div class='error'>";
                    echo __($guid, 'This column is part of a set of columns, which you did not create, and so cannot be individually edited.');
                    echo '</div>';
                } else {
                    if (isset($_GET['return'])) {
                        returnProcess($guid, $_GET['return'], null, array('error3' => __('Your request failed due to an attachment error.')));
                    }

                    $form = Form::create('internalAssessment', $_SESSION[$guid]['absoluteURL'].'/modules/'.$_SESSION[$guid]['module'].'/internalAssessment_manage_editProcess.php?gibbonInternalAssessmentColumnID='.$gibbonInternalAssessmentColumnID.'&gibbonCourseClassID='.$gibbonCourseClassID.'&address='.$_SESSION[$guid]['address']);
                    $form->addHiddenValue('address', $_SESSION[$guid]['address']);
        
                    $form->addRow()->addHeading(__('Basic Information'));
        
                    $row = $form->addRow();
                        $row->addLabel('className', __('Class'));
                        $row->addTextField('className')->isRequired()->readonly()->setValue(htmlPrep($class['course'].'.'.$class['class']));
        
                    $row = $form->addRow();
                        $row->addLabel('name', __('Name'));
                        $row->addTextField('name')->isRequired()->maxLength(20);
        
                    $row = $form->addRow();
                        $row->addLabel('description', __('Description'));
                        $row->addTextField('description')->isRequired()->maxLength(1000);
        
                    $types = getSettingByScope($connection2, 'Formal Assessment', 'internalAssessmentTypes');
                    if (!empty($types)) {
                        $row = $form->addRow();
                            $row->addLabel('type', __('Type'));
                            $row->addSelect('type')->fromString($types)->isRequired()->placeholder();
                    }
        
                    $row = $form->addRow();
                        $row->addLabel('file', __('Attachment'));
                        $row->addFileUpload('file')->setAttachment('attachment', $_SESSION[$guid]['absoluteURL'], $values['attachment']);
        
                    $form->addRow()->addHeading(__('Assessment'));
        
                    $sql = "SELECT gibbonScaleID as value, name FROM gibbonScale WHERE (active='Y') ORDER BY name";
                    $result = $pdo->executeQuery(array(), $sql);
                    $gradeScales = ($result->rowCount() > 0)? $result->fetchAll(\PDO::FETCH_KEY_PAIR) : array();
        
                    $attainmentLabel = !empty($attainmentAlternativeName)? sprintf(__('Assess %1$s?'), $attainmentAlternativeName) : __('Assess Attainment?');
                    $row = $form->addRow();
                        $row->addLabel('attainment', $attainmentLabel);
                        $row->addYesNoRadio('attainment')->isRequired();
        
                    $form->toggleVisibilityByClass('attainmentRow')->onRadio('attainment')->when('Y');
        
                    $attainmentScaleLabel = !empty($attainmentAlternativeName)? $attainmentAlternativeName.' '.__('Scale') : __('Attainment Scale');
                    $row = $form->addRow()->addClass('attainmentRow');
                        $row->addLabel('gibbonScaleIDAttainment', $attainmentScaleLabel);
                        $row->addSelect('gibbonScaleIDAttainment')
                            ->fromArray($gradeScales)
                            ->selected($_SESSION[$guid]['defaultAssessmentScale'])
                            ->isRequired()
                            ->placeholder();
        
                    $effortLabel = !empty($effortAlternativeName)? sprintf(__('Assess %1$s?'), $effortAlternativeName) : __('Assess Effort?');
                    $row = $form->addRow();
                        $row->addLabel('effort', $effortLabel);
                        $row->addYesNoRadio('effort')->isRequired();
        
                    $form->toggleVisibilityByClass('effortRow')->onRadio('effort')->when('Y');
        
                    $effortScaleLabel = !empty($effortAlternativeName)? $effortAlternativeName.' '.__('Scale') : __('Effort Scale');
                    $row = $form->addRow()->addClass('effortRow');
                        $row->addLabel('gibbonScaleIDEffort', $effortScaleLabel);
                        $row->addSelect('gibbonScaleIDEffort')
                            ->fromArray($gradeScales)
                            ->selected($_SESSION[$guid]['defaultAssessmentScale'])
                            ->isRequired()
                            ->placeholder();
        
                    $row = $form->addRow();
                        $row->addLabel('comment', __('Include Comment?'));
                        $row->addYesNoRadio('comment')->isRequired();
        
                    $row = $form->addRow();
                        $row->addLabel('uploadedResponse', __('Include Uploaded Response?'));
                        $row->addYesNoRadio('uploadedResponse')->isRequired();
        
                    $form->addRow()->addHeading(__('Access'));
        
                    $row = $form->addRow();
                        $row->addLabel('viewableStudents', __('Viewable to Students'));
                        $row->addYesNo('viewableStudents')->isRequired();
        
                    $row = $form->addRow();
                        $row->addLabel('viewableParents', __('Viewable to Parents'));
                        $row->addYesNo('viewableParents')->isRequired();
        
                    $row = $form->addRow();
                        $row->addLabel('completeDate', __('Go Live Date'))->prepend('1. ')->append('<br/>'.__('2. Column is hidden until date is reached.'));
                        $row->addDate('completeDate');
        
                    $row = $form->addRow();
                        $row->addFooter();
                        $row->addSubmit();

                    $form->loadAllValuesFrom($values);
        
                    echo $form->getOutput();
                }
            }
        }

        //Print sidebar
        $_SESSION[$guid]['sidebarExtra'] = sidebarExtra($guid, $connection2, $gibbonCourseClassID);
    }
}
