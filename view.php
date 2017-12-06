<?php // $Id: view.php,v 1.2 2012/03/10 22:00:00 Igor Nikulin Exp $


require_once '../../config.php';
require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once 'lib.php';
require_once($CFG->libdir . '/gradelib.php');

$id = optional_param('id', 0, PARAM_INT);
$ids = optional_param('ids', 0, PARAM_INT);
$a = optional_param('a', 'list', PARAM_TEXT);
$summary = optional_param_array('summary', NULL, PARAM_TEXT);
$filename = optional_param('filename', NULL, PARAM_TEXT);
$fileid = optional_param('fileid', 0, PARAM_INT);
$submitfile = optional_param('submitfile', 0, PARAM_INT);
$commentid = optional_param('commentid', 0, PARAM_INT);
$act = optional_param('act', NULL, PARAM_CLEAN);
$itemyoutube = optional_param('itemyoutube', NULL, PARAM_CLEAN);


if (is_array($summary)) $summary = $summary['text'];

if ($id) {
    if (!$cm = get_coursemodule_from_id('videoboard', $id)) {
        error('Course Module ID was incorrect');
    }

    if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
        error('Course is misconfigured');
    }

    if (!$videoboard = $DB->get_record('videoboard', array('id' => $cm->instance))) {
        error('Course module is incorrect');
    }

} else {
    error('You must specify a course_module ID or an instance ID');
}

require_login($course, true, $cm);

$context = context_module::instance($cm->id);


$PAGE->requires->js('/mod/videoboard/js/jquery.min.js', true);


$PAGE->requires->js('/mod/videoboard/js/flowplayer.min.js', true);
$PAGE->requires->js('/mod/videoboard/js/swfobject.js', true);
$PAGE->requires->js('/mod/videoboard/js/WebAudioRecorder.min.js', true);


$PAGE->requires->js('/mod/videoboard/js/mediaelement-and-player.min.js', true);
$PAGE->requires->css('/mod/videoboard/css/mediaelementplayer.css');
$PAGE->requires->css('/mod/videoboard/css/main.css');

$PAGE->requires->js('/mod/videoboard/js/video.js', true);
$PAGE->requires->css('/mod/videoboard/css/video-js.css');


///Get unical id for video records
$unicalid = (int)substr(time(), 2) . rand(0, 9);
$unicalid = $unicalid + 0;

///Uploading MOV video from device
if (!empty($_FILES['mov_video']['tmp_name'])) {
    $fs = get_file_storage();

    $ext = explode(".", $_FILES['mov_video']['name']);
    $ext = strtolower(end($ext));

    $file_record = new stdClass();
    $file_record->component = 'mod_videoboard';
    $file_record->contextid = $context->id;
    $file_record->userid = $USER->id;
    $file_record->filearea = 'private';
    $file_record->filepath = "/";
    $file_record->itemid = $unicalid;
    $file_record->license = $CFG->sitedefaultlicense;
    $file_record->author = fullname($USER);
    $file_record->source = '';
    $file_record->filename = $filename . "." . $ext;
    $itemid = $fs->create_file_from_pathname($file_record, $_FILES['mov_video']['tmp_name']);

    $submitfile = $itemid->get_id();
}

///Uploading mobile audio
if (!empty($_FILES['mobile_audio']['tmp_name'])) {
    $fs = get_file_storage();

    $ext = strtolower(end(explode(".", $_FILES['mobile_audio']['name'])));

    $file_record = new stdClass();
    $file_record->component = 'mod_videoboard';
    $file_record->contextid = $context->id;
    $file_record->userid = $USER->id;
    $file_record->filearea = 'private';
    $file_record->filepath = "/";
    $file_record->itemid = $unicalid;
    $file_record->license = $CFG->sitedefaultlicense;
    $file_record->author = fullname($USER);
    $file_record->source = '';
    $file_record->filename = $filename . "." . $ext;
    $itemid = $fs->create_file_from_pathname($file_record, $_FILES['mobile_audio']['tmp_name']);

    $submitfile = $itemid->get_id();

///Quick mime type fixer
    if ($ext == "3gpp") {
        $add = new stdClass();
        $add->id = $submitfile;
        $add->mimetype = 'audio/3gpp';

        $DB->update_record("files", $add);
        //$DB->execute("UPDATE {files} SET `mimetype`='audio/3gpp' WHERE `id` ={$submitfile}");
    }

    if ($ext == "mov") {
        $add = new stdClass();
        $add->id = $submitfile;
        $add->mimetype = 'audio/mp3';
        $add->filename = '.mp3';

        $DB->update_record("files", $add);
        //$DB->execute("UPDATE {files} SET `mimetype`='audio/mp3' AND `filename`='.mp3' WHERE `id` ={$submitfile}");
    }
}


if ($act == "addlike") {
    if (!$DB->get_record("videoboard_likes", array("fileid" => $fileid, "userid" => $USER->id))) {
        $add = new stdClass();
        $add->instance = $id;
        $add->fileid = $fileid;
        $add->userid = $USER->id;
        $add->time = time();

        $DB->insert_record("videoboard_likes", $add);
    }
}


if ($act == "dellike") {
    $DB->delete_records("videoboard_likes", array("fileid" => $fileid, "userid" => $USER->id));
}


if ($a == 'add' && $act == 'newinstance') {
    $data = new stdClass();
    $data->instance = $id;
    $data->userid = $USER->id;
    $data->summary = $summary;
    $data->filename = $filename;
    $data->time = time();

    if (!empty($itemyoutube))
        if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $itemyoutube, $match))
            $data->itemyoutube = $match[1];

    if (!empty($submitfile)) {
        if ($file = videoboard_getfile($submitfile)) {
            $data->itemoldid = $file->id;
///Update submited file params
            $DB->execute("UPDATE {files}
SET `contextid`={$context->id}, 
`component`='mod_videoboard', 
`filearea`='private', 
`itemid`={$unicalid}
WHERE  `component` LIKE  'user'
AND  `filearea` LIKE  'draft'
AND  `itemid` ={$submitfile}");

///No convert
            if ($file->mimetype == "audio/mp3") {
                $data->itemid = $file->id;
            }
        } else if ($file = videoboard_getfileid($submitfile)) {
            $data->itemoldid = $file->id;
        }

        if (!empty($data->itemoldid) && empty($data->itemid)) {
            $add = new stdClass();
            $add->itemid = $file->id;
            $add->type = $file->mimetype;
            $add->status = 'open';
            $add->name = md5($CFG->wwwroot . '_' . time());
            $add->time = time();

            $DB->insert_record("videoboard_process", $add);
        }
    }

    if (!empty($fileid)) {
        $data->id = $fileid;
        $DB->update_record("videoboard_files", $data);
    } else
        $DB->insert_record("videoboard_files", $data);

    redirect("view.php?id={$id}", get_string('postsubmited', 'videoboard'));
}

if ($a == 'add' && $act == 'addcomment' && isset($summary)) {
    $data = new stdClass();
    $data->instance = $id;
    $data->userid = $USER->id;
    $data->summary = $summary;
    $data->filename = $filename;
    $data->fileid = $fileid;
    $data->time = time();


    if (!empty($submitfile)) {
        if ($file = videoboard_getfile($submitfile)) {
            $data->itemoldid = $file->id;

///Update submited file params
            $DB->execute("UPDATE {files}
SET `contextid`={$context->id}, 
`component`='mod_videoboard', 
`filearea`='private', 
`itemid`={$unicalid}
WHERE  `component` LIKE  'user'
AND  `filearea` LIKE  'draft'
AND  `itemid` ={$submitfile}");

///No convert
            if ($file->mimetype == "audio/mp3") {
                $data->itemid = $file->id;
            }
        } else if ($file = videoboard_getfileid($submitfile)) {
            $data->itemoldid = $file->id;
        }

        if (!empty($data->itemoldid) && empty($data->itemid)) {
            $add = new stdClass();
            $add->itemid = $file->id;
            $add->type = $file->mimetype;
            $add->status = 'open';
            $add->name = md5($CFG->wwwroot . '_' . time());
            $add->time = time();

            $DB->insert_record("videoboard_process", $add);
        }
    }


    if (!empty($commentid)) {
        $data->id = $commentid;
        $DB->update_record("videoboard_comments", $data);
    } else
        $DB->insert_record("videoboard_comments", $data);


    redirect("view.php?id={$id}", get_string('commentsubmited', 'videoboard'));
}


if ($act == "deleteentry" && !empty($fileid)) {
    if (has_capability('mod/videoboard:teacher', $context))
        $DB->delete_records("videoboard_files", array("id" => $fileid));
    else
        $DB->delete_records("videoboard_files", array("id" => $fileid, "userid" => $USER->id));
}

if ($act == "deleteentry" && !empty($filename)) {
    $filename = end(explode("/", $filename));
    list($filename) = explode(".", $filename);
    $DB->delete_records("videoboard_files", array("filename" => $filename, "userid" => $USER->id));
}

if ($act == "deletecomment" && !empty($fileid)) {
    if (has_capability('mod/videoboard:teacher', $context))
        $DB->delete_records("videoboard_comments", array("id" => $fileid));
    else
        $DB->delete_records("videoboard_comments", array("id" => $fileid, "userid" => $USER->id));
}


/// Print the page header
$strvideoboards = get_string('modulenameplural', 'videoboard');
$strvideoboard = get_string('modulename', 'videoboard');

$PAGE->set_url('/mod/videoboard/view.php', array('id' => $id));

$title = $course->shortname . ': ' . format_string($videoboard->name);
$PAGE->set_title($title);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

/// Print the main part of the page

require_once('tabs.php');

if ($a == "list") {

    if ($videoboard->grademethodt == "rubrics") {
        echo html_writer::start_tag('div');
        echo html_writer::link(new moodle_url('/mod/videoboard/submissions.php', array("id" => $id)), get_string("rubrics", "videoboard"));
        echo html_writer::end_tag('div');
    }

    $table = new html_table();
    $table->width = "100%";

    if ($videoboard->grademethod == "like")
        $peertext = get_string("cell3::peerfeedback", "videoboard") . html_writer::empty_tag("br") . html_writer::empty_tag("img", array("src" => new moodle_url('/mod/videoboard/img/flike.png'), "alt" => get_string("likethis", "videoboard"), "title" => get_string("dislike", "videoboard"), "class" => "vs-like"));
    else
        $peertext = get_string("cell3::peer", "videoboard");


    if (!videoboard_is_ios()) {
        $table->head = array(get_string("cell1::student", "videoboard"), get_string("cell2::", "videoboard"), $peertext, get_string("cell4::teacher", "videoboard"));
        $table->align = array("left", "center", "center", "center");
    }


    $lists = $DB->get_records("videoboard_files", array("instance" => $id), 'time DESC');

    foreach ($lists as $list) {
        $userdata = $DB->get_record("user", array("id" => $list->userid));
        $picture = $OUTPUT->user_picture($userdata, array('popup' => true));

        $own = $DB->get_record("videoboard_ratings", array("fileid" => $list->id, "userid" => $list->userid));

        if (empty($own->ratingrhythm)) @$own->ratingrhythm = get_string('norateyet', 'videoboard');
        if (empty($own->ratingclear)) $own->ratingclear = get_string('norateyet', 'videoboard');
        if (empty($own->ratingintonation)) $own->ratingintonation = get_string('norateyet', 'videoboard');
        if (empty($own->ratingspeed)) $own->ratingspeed = get_string('norateyet', 'videoboard');
        if (empty($own->ratingreproduction)) $own->ratingreproduction = get_string('norateyet', 'videoboard');

        //1-cell
        $o = "";
        $o .= html_writer::start_tag('div', array("style" => "text-align:left;margin:10px 0;"));
        $o .= html_writer::tag('span', $picture);
        $o .= html_writer::start_tag('span', array("style" => "margin: 8px;position: absolute;"));
        $o .= html_writer::link(new moodle_url('/user/view.php', array("id" => $userdata->id, "course" => $cm->course)), fullname($userdata));
        $o .= html_writer::end_tag('span');
        $o .= html_writer::end_tag('div');

        $o .= html_writer::tag('div', $list->summary, array('style' => 'margin:10px 0;'));

        $o .= html_writer::tag('div', videoboard_player($list->id));

        $o .= html_writer::tag('div', html_writer::tag('small', date(get_string("timeformat1", "videoboard"), $list->time)), array("style" => "float:left;"));

        if ($list->userid == $USER->id || has_capability('mod/videoboard:teacher', $context)) {
            if ($list->userid == $USER->id)
                $editlink = html_writer::link(new moodle_url('/mod/videoboard/view.php', array("id" => $id, "a" => "add", "fileid" => $list->id)), get_string("editlink", "videoboard")) . " ";
            else
                $editlink = "";

            if (has_capability('mod/videoboard:teacher', $context) || ($videoboard->resubmit == 1 && $list->userid == $USER->id))
                $deletelink = html_writer::link(new moodle_url('/mod/videoboard/view.php', array("id" => $id, "act" => "deleteentry", "fileid" => $list->id)), get_string("delete", "videoboard"), array("onclick" => "return confirm('" . get_string("confim", "videoboard") . "')"));
            else
                $deletelink = "";

            $o .= html_writer::tag('div', html_writer::tag('small', $editlink . $deletelink, array("style" => "margin: 2px 0 0 10px;")));
        }

        $cell1 = new html_table_cell($o);

        //2-cell
        $table2 = new html_table();
        $table2->width = "100%";

        if (videoboard_is_ios()) {
            $table2->data[] = new html_table_row(array(new html_table_cell(get_string("table2::cell1::pronunciation", "videoboard")), new html_table_cell(videoboard_set_rait($list->id, 1))));
            $table2->data[] = new html_table_row(array(new html_table_cell(get_string("table2::cell2::fluency", "videoboard")), new html_table_cell(videoboard_set_rait($list->id, 2))));
            $table2->data[] = new html_table_row(array(new html_table_cell(get_string("table2::cell3::content", "videoboard")), new html_table_cell(videoboard_set_rait($list->id, 3))));
            $table2->data[] = new html_table_row(array(new html_table_cell(get_string("table2::cell4::organization", "videoboard", "videoboard")), new html_table_cell(videoboard_set_rait($list->id, 4))));
            $table2->data[] = new html_table_row(array(new html_table_cell(get_string("table2::cell5::eye", "videoboard")), new html_table_cell(videoboard_set_rait($list->id, 5))));
        } else {
            $table2->head = array(get_string("table2::cell1::pronunciation", "videoboard"), get_string("table2::cell2::fluency", "videoboard"), get_string("table2::cell3::content", "videoboard"), get_string("table2::cell4::organization", "videoboard"), get_string("table2::cell5::eye", "videoboard"));
            //$table2->align = array ("center", "center", "center", "center", "center");
            $table2->align = array("center" . get_string("table2::style", "videoboard"), "center" . get_string("table2::style", "videoboard"), "center" . get_string("table2::style", "videoboard"), "center" . get_string("table2::style", "videoboard"), "center" . get_string("table2::style", "videoboard"));

            $table2->data[] = array(videoboard_set_rait($list->id, 1),
                videoboard_set_rait($list->id, 2),
                videoboard_set_rait($list->id, 3),
                videoboard_set_rait($list->id, 4),
                videoboard_set_rait($list->id, 5));
        }

        //----Comment Box-----/
        //if ($list->userid == $USER->id){
        $chtml = "";
        if ($comments = $DB->get_records("videoboard_comments", array("fileid" => $list->id))) {
            foreach ($comments as $comment) {
                $chtml .= html_writer::start_tag('div', array("style" => "border:1px solid #333;margin:5px;text-align:left;padding:5px;"));

                $chtml .= html_writer::tag('div', $comment->summary, array('style' => 'margin:10px 0;'));

                if (!empty($comment->itemid)) {
                    $chtml .= html_writer::tag('div', videoboard_player($comment->id, "videoboard_comments"));
                }

                $chtml .= html_writer::tag('div', html_writer::tag('small', date(get_string("timeformat1", "videoboard"), $comment->time)), array("style" => "float:left;"));

                $student = $DB->get_record("user", array("id" => $comment->userid));
                $studentlink = html_writer::link(new moodle_url('/user/view.php', array("id" => $student->id, "course" => $cm->course)), fullname($student));

                //if ($comment->userid == $USER->id || has_capability('mod/videoboard:teacher', $context)) {
                if (has_capability('mod/videoboard:teacher', $context) || ($videoboard->resubmit == 1 && $comment->userid == $USER->id)) {
                    $deletelink = html_writer::link(new moodle_url('/mod/videoboard/view.php', array("id" => $id, "act" => "deletecomment", "fileid" => $comment->id)), get_string("delete", "videoboard"), array("onclick" => "return confirm('" . get_string("confim", "videoboard") . "')"));
                } else {
                    $deletelink = "";
                }

                if (has_capability('mod/videoboard:teacher', $context) && $comment->userid == $USER->id) {
                    $editlink = html_writer::link(new moodle_url('/mod/videoboard/view.php', array("id" => $id, "a" => "add", "act" => "addcomment", "fileid" => $list->id, "commentid" => $comment->id)), get_string("editlink", "videoboard"));
                } else {
                    $editlink = "";
                }
                // }

                $chtml .= html_writer::tag('div', html_writer::tag('small', $studentlink . " " . $editlink . " " . $deletelink, array("style" => "margin: 2px 0 0 10px;")));

                $chtml .= html_writer::tag('div', NULL, array("style" => "clear:both"));

                $chtml .= html_writer::end_tag('div');
            }
        }

        if (has_capability('mod/videoboard:teacher', $context)) {
            $addcommentlink = html_writer::tag('div', html_writer::link(new moodle_url('/mod/videoboard/view.php', array("id" => $id, "a" => "add", "act" => "addcomment", "fileid" => $list->id)), get_string("addcomment", "videoboard")));
        } else {
            $addcommentlink = "";
        }

        /*
      } else
        $addcomment = "";*/
        //--------------------/

        if (videoboard_is_ios()) {
            //if ($list->userid != $USER->id){
            //  unset($table2->data);
            //}

            $table2->data[] = new html_table_row(array(new html_table_cell($peertext), new html_table_cell(videoboard_set_rait($list->id, 6))));
            $table2->data[] = new html_table_row(array(new html_table_cell(get_string("cell4::teacher", "videoboard")), new html_table_cell(videoboard_set_rait($list->id, 7))));

            $row = new html_table_row(array($cell1));
            $table->data[] = $row;

            $cell2 = new html_table_cell(html_writer::table($table2) . $chtml . $addcommentlink);
            $row = new html_table_row(array($cell2));
            $table->data[] = $row;
        } else {
            //if ($list->userid == $USER->id)
            $cell2 = new html_table_cell(html_writer::table($table2) . $chtml . $addcommentlink);
            //else
            //  $cell2 = new html_table_cell($chtml . $addcommentlink);

            //3-cell
            $cell3 = new html_table_cell(videoboard_set_rait($list->id, 6));

            //4-cell
            $cell4 = new html_table_cell(videoboard_set_rait($list->id, 7));


            $cells = array($cell1, $cell2, $cell3, $cell4);

            $row = new html_table_row($cells);

            $table->data[] = $row;
        }
    }

    echo html_writer::table($table);

    echo html_writer::script('
 $(document).ready(function() {
  $(".videoboard_rate_box").change(function() {
    var value = $(this).val();
    var data  = $(this).attr("data-url");
    
    var e = $(this).parent();
    e.html(\'<img src="img/ajax-loader.gif" />\');
    
    $.get("ajax.php", {id: ' . $id . ', act: "setrating", data: data, value: value}, function(data) {
      e.html(data); 
    });
  });
 });
    ');

    /*
if (is_object($table)) {
    list($totalcount, $table->data, $startrec, $finishrec, $options["page"]) = videoboard_get_pages($table->data, $page, $perpage);
    print_paging_bar($totalcount, $page, $perpage, "view.php?a=list&id={$id}&sort={$sort}&orderby={$orderby}&amp;");
    print_table($table);
    print_paging_bar($totalcount, $page, $perpage, "view.php?a=list&id={$id}&sort={$sort}&orderby={$orderby}&amp;");
}
*/
}


if ($a == "add") {
    class videoboard_comment_form extends moodleform
    {
        function definition()
        {
            global $CFG, $USER, $DB, $course, $fileid, $id, $act, $commentid;

            $time = time();
            $filename = str_replace(" ", "_", $USER->username) . "_" . date("Ymd_Hi", $time);

            $mform =& $this->_form;

            $mform->updateAttributes(array('enctype' => 'multipart/form-data'));

            //--------------Uploadd MP3 ----------------//
            if (!videoboard_is_ios()) {
                $filepickeroptions = array();
                //$filepickeroptions['filetypes'] = array('.mp3','.mov','.mp4','.m4a');
                $filepickeroptions['maxbytes'] = get_max_upload_file_size($CFG->maxbytes);
                $mform->addElement('header', 'mp3upload', get_string('mp3upload', 'videoboard'));
                $mform->addElement('filepicker', 'submitfile', get_string('uploadmp3', 'videoboard'), null, $filepickeroptions);
            }

            $mform->addElement('header', 'addcomment', get_string('addcomment', 'videoboard'));

            $youtubeurl = "";
            if (!empty($fileid) && empty($act)) {
                $data = $DB->get_record("videoboard_files", array("id" => $fileid, "userid" => $USER->id));
                $mform->addElement('editor', 'summary', '')->setValue(array('text' => $data->summary));
                $youtubeurl = $data->itemyoutube;
            } else {
                if (!empty($act) && !empty($commentid)) {
                    $data = $DB->get_record("videoboard_comments", array("id" => $commentid, "userid" => $USER->id));
                    $mform->addElement('editor', 'summary', '')->setValue(array('text' => $data->summary));
                    $mform->addelEment('hidden', 'commentid', $commentid);
                } else
                    $mform->addElement('editor', 'summary', '');
            }

            //-------------- Record ----------------//
            $mediadatavoice = "";
            $mediadatavideo = "";

            if (videoboard_is_ios()) {
                $mediadatavoice .= html_writer::start_tag("h3", array("style" => "padding: 0 20px;"));

                $mediadatavoice .= get_string('recordvoice', 'videoboard');

                $mediadatavoice .= html_writer::end_tag('h3');

                $mediadatavideo .= html_writer::empty_tag("input", array("type" => "file", "name" => "mov_video", "accept" => "video/*")); //, "capture" => "camcorder"

                $mediadatavoice .= html_writer::script('function formsubmit(link) {$(\'input[name=iphonelink]\').val(link);$(\'#mform1\').submit();}');

                $mediadatavoice .= html_writer::start_tag('div');
                $mediadatavoice .= html_writer::empty_tag("input", array("type" => "file", "name" => "mobile_audio", "accept" => "audio/*", "capture" => "microphone"));
                $mediadatavoice .= html_writer::end_tag('div');
            } else if (videoboard_get_browser() == 'android') {
                $mediadatavideo .= html_writer::empty_tag("input", array("type" => "file", "name" => "mov_video", "accept" => "video/*")); //, "capture" => "camcorder"

                $mediadatavoice .= html_writer::empty_tag("input", array("type" => "file", "name" => "mobile_audio", "accept" => "audio/*", "capture" => "microphone"));
            } else {
                $mediadatavoice .= '

  <div style="font-size: 21px;line-height: 40px;color: #333;">Record</div>

  <img src="img/spiffygif_30x30.gif" style="display:none;" id="html5-mp3-loader"/>
  <button onclick="startRecording(this);" id="btn_rec" disabled>record</button>
  <button onclick="stopRecording(this);" id="btn_stop" disabled>stop</button>

  <div style="font-size: 21px;line-height: 40px;color: #333;">Recordings</div>
  <ul id="recordingslist" style="list-style-type: none;"></ul>

  <div style="font-size: 21px;line-height: 40px;color: #333;display:none;">Log</div>
  <pre id="log" style="display:none"></pre>

  <script>

  $(".selectaudiomodel").click(function(){
    $("#audioshadowmp3").attr("src", $(this).parent().find("audio").attr("src"));
    __log($(this).parent().find("audio").attr("src"));
  });

  function __log(e, data) {
    log.innerHTML += "\n" + e + " " + (data || \'\');
  }

  var audio_context;
  var recorder;

  function startUserMedia(stream) {
    var input = audio_context.createMediaStreamSource(stream);
    __log(\'Media stream created.\' );
    __log("input sample rate " +input.context.sampleRate);

    //input.connect(audio_context.destination);
    //__log(\'Input connected to audio context destination.\');

    recorder = new Recorder(input, {
                  numChannels: 1,
                  sampleRate: 48000,
                });
    __log(\'Recorder initialised.\');
  }

  function startRecording(button) {
    recorder.startRecording();
    button.disabled = true;
    button.nextElementSibling.disabled = false;
    __log(\'Recording...\');
  }

  function stopRecording(button) {
    recorder.finishRecording();
    button.disabled = true;
    button.previousElementSibling.disabled = false;
    __log(\'Stopped recording.\');
  }

  window.onload = function init() {
    // navigator.getUserMedia shim
    navigator.getUserMedia =
      navigator.getUserMedia ||
      navigator.webkitGetUserMedia ||
      navigator.mozGetUserMedia ||
      navigator.msGetUserMedia;
    
    // URL shim
    window.URL = window.URL || window.webkitURL;
    
    // audio context + .createScriptProcessor shim
    var audioContext = new AudioContext;
    if (audioContext.createScriptProcessor == null)
      audioContext.createScriptProcessor = audioContext.createJavaScriptNode;
    
    var testTone = (function() {
      var osc = audioContext.createOscillator(),
          lfo = audioContext.createOscillator(),
          ampMod = audioContext.createGain(),
          output = audioContext.createGain();
      lfo.type = \'square\';
      lfo.frequency.value = 2;
      osc.connect(ampMod);
      lfo.connect(ampMod.gain);
      output.gain.value = 0.5;
      ampMod.connect(output);
      osc.start();
      lfo.start();
      return output;
    })();
    
   
    var testToneLevel = audioContext.createGain(),
        microphone = undefined,     // obtained by user click
        microphoneLevel = audioContext.createGain(),
        mixer = audioContext.createGain();
    
    testTone.connect(testToneLevel);
    testToneLevel.gain.value = 0;
    //testToneLevel.connect(mixer);
    microphoneLevel.gain.value = 0.5;
    microphoneLevel.connect(mixer);
    //mixer.connect(audioContext.destination);

      if (microphone == null)
        navigator.getUserMedia({ audio: true },
          function(stream) {
            microphone = audioContext.createMediaStreamSource(stream);
            microphone.connect(microphoneLevel);
          },
          function(error) {
          console.log("Could not get audio input.");
            audioRecorder.onError(audioRecorder, "Could not get audio input.");
          });
    
    
        recorder = new WebAudioRecorder(mixer, {
          workerDir: "js/"
        });
        
        recorder.setEncoding("mp3");
        
          recorder.setOptions({
        timeLimit: 300,
        mp3: { bitRate: 64 }
      });
    
    recorder.onComplete = function(recorder, blob) {
      window.LatestBlob = blob;
      
      var time = new Date(),
      url = URL.createObjectURL(blob),
      html = "<p recording=\'" + url + "\'>" +
             "<audio controls src=\'" + url + "\'></audio> " +
             "</p>";
      
      $("#recordingslist").html(html);
                    
      //saveRecording(blob, recorder.encoding);
      uploadAudio(blob);
      
    };
    
  };
  
  	
	function uploadAudio(mp3Data){
		var reader = new FileReader();
		reader.onload = function(event){
			var fd = new FormData();
			var mp3Name = encodeURIComponent(\'audio_recording_\' + new Date().getTime() + \'.mp3\');
			console.log("mp3name = " + mp3Name);
			fd.append(\'name\', mp3Name);
			fd.append(\'p\', $(\'#audioshadowmp3\').attr("data-url"));
			fd.append(\'audio\', event.target.result);
			$.ajax({
				type: \'POST\',
				url: \'uploadmp3.php\',
				data: fd,
				processData: false,
				contentType: false
			}).done(function(data) {
				//console.log(data);
				obj = JSON.parse(data);
				$("#id_submitfile").val(obj.id);
				
				log.innerHTML += "\n" + data;
			});
		};      
		reader.readAsDataURL(mp3Data);
	}

  function jInit(){
      audio = $("#audioshadowmp3");
      addEventHandlers();
  }

  function addEventHandlers(){
      $("#btn_rec").click(startAudio);
      $("#btn_stop").click(stopAudio);
  }

  function loadAudio(){
      audio.bind("load",function(){
        __log(\'MP3 Audio Loaded succesfully\');
        $(\'#btn_rec\').removeAttr( "disabled" );
      });
      audio.trigger(\'load\');
      //startAudio()
  }

  function startAudio(){
      __log(\'MP3 Audio Play\');
      audio.trigger(\'play\');
  }

  function pauseAudio(){
      __log(\'MP3 Audio Pause\');
      audio.trigger(\'pause\');
  }

  function stopAudio(){
      pauseAudio();
      audio.prop("currentTime",0);
  }

  function forwardAudio(){
      pauseAudio();
      audio.prop("currentTime",audio.prop("currentTime")+5);
      startAudio();
  }

  function backAudio(){
      pauseAudio();
      audio.prop("currentTime",audio.prop("currentTime")-5);
      startAudio();
  }

  function volumeUp(){
      var volume = audio.prop("volume")+0.2;
      if(volume >1){
        volume = 1;
      }
      audio.prop("volume",volume);
  }

  function volumeDown(){
      var volume = audio.prop("volume")-0.2;
      if(volume <0){
        volume = 0;
      }
      audio.prop("volume",volume);
  }

  function toggleMuteAudio(){
      audio.prop("muted",!audio.prop("muted"));
  }

  $( document ).ready(function() {
     jInit();
     loadAudio();
  });
</script>';

                $mediadatavoice .= ' <audio src="" id="audioshadowmp3" autobuffer="autobuffer" data-url="' . urlencode(json_encode(array("id" => $id, "userid" => $USER->id))) . '"></audio>
                  ';
            }

            $mform->addElement('header', 'Recording', get_string('recordvoice', 'videoboard'));
            $mform->addelEment('hidden', 'filename', $filename);
            $mform->addelEment('hidden', 'iphonelink', '');
            $mform->addElement('static', 'description', '', $mediadatavoice);

            if (!empty($mediadatavideo)) {
                $mform->addElement('header', 'Recording', get_string('recordvideo', 'videoboard'));
                $mform->addElement('static', 'description', '', $mediadatavideo);
            }

            if (!empty($fileid) && empty($act)) {
                $mform->setDefault("filename", $data->filename);
                $mform->addelEment('hidden', 'fileid', $fileid);
            }

            if (!empty($act)) {
                $mform->addelEment('hidden', 'act', $act);
                $mform->addelEment('hidden', 'fileid', $fileid);
            } else
                $mform->addelEment('hidden', 'act', 'newinstance');
            //-------------- Record -------END------//


            $mform->addElement('header', 'youtubevideo_header', get_string('youtubevideo', 'videoboard'));
            $mform->addElement('textarea', 'itemyoutube', '', 'wrap="virtual" rows="5" cols="100"')->setValue($youtubeurl);


            $mform->setType('fileid', PARAM_INT);
            $mform->setType('act', PARAM_TEXT);
            $mform->setType('filename', PARAM_TEXT);
            $mform->setType('iphonelink', PARAM_TEXT);


            $this->add_action_buttons(false, $submitlabel = get_string("saverecording", "videoboard"));
        }
    }

    $mform = new videoboard_comment_form('view.php?a=' . $a . '&id=' . $id);

    $mform->display();
}

echo html_writer::script('
$(document).ready(function() {
  $(".videoboard-youtube-poster").click(function() {
    $("#videoboard-player-"+$(this).attr("data-url")).html(\'<iframe type="text/html" width="269" height="198" src="https://www.youtube.com/embed/\'+$(this).attr("data-text")+\'" frameborder="0"></iframe>\');
  });
  
  $(".mediaelementplayer").mediaelementplayer();
});');

/// Finish the page
echo $OUTPUT->footer();



