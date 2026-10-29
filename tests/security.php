<?php
// Standalone regression tests: no WordPress database, mail or network calls.
define('WPINC', 'wp-includes');
function add_filter(...$args) {}
function add_action(...$args) {}
function apply_filters($hook,$value,...$args) { return $value; }
function get_current_user_id() { return $GLOBALS['user_id']; }
function get_userdata($id) { return $GLOBALS['users'][$id] ?? false; }
function is_admin() { return false; }
function wp_unslash($s) { return stripslashes($s); }
function wp_json_encode($s) { return json_encode($s); }
function sanitize_text_field($s) { return strip_tags((string)$s); }
function sanitize_key($s) { return preg_replace('/[^a-z0-9_\-]/','',strtolower($s)); }
function sanitize_file_name($s) { return basename($s); }
function trailingslashit($s) { return rtrim($s,'/').'/'; }
function __($s,...$args) { return $s; }
function wpcf7_contact_form($id) { return $GLOBALS['forms'][$id] ?? false; }
class wpdb {
    public $prefix='wp_'; public $row; public $queries=0; public $bindings=[];
    function prepare($query,...$values) { $this->bindings=[$query,$values];return $query; }
    function get_row($query) { $this->queries++;return $this->row; }
    function get_col($query,$column=0) { return ['id','form_id','data','owner','group_owner','permissions','object_name']; }
}
class WPCF7_ContactForm {
    public $skip_mail=false;
    function id() { return 7; }
}
class TestForm extends WPCF7_ContactForm {
    public $tags;
    function __construct($tags=[]) { $this->tags=$tags; }
    function scan_form_tags() { return $this->tags; }
}
$GLOBALS['user_id']=0;
$GLOBALS['users']=[9=>(object)['roles'=>['staff']],10=>(object)['roles'=>['author']],1=>(object)['roles'=>['administrator']]];
$GLOBALS['forms']=[7=>new TestForm(),8=>new TestForm([(object)['name'=>'far_db_column_owner','values'=>['9']],(object)['name'=>'far_db_column_permissions','values'=>['700']]])];
$wpdb=new wpdb();
require dirname(__DIR__).'/modules/general.php';
require dirname(__DIR__).'/modules/update.php';
function plugin_dir_path($file) { return dirname($file).'/'; }
require dirname(__DIR__).'/modules/router.php';
function check($condition,$label) { if(!$condition)throw new RuntimeException($label);echo "PASS $label\n"; }
$row=(object)['id'=>'1790000000abcdef1234','form_id'=>'7','data'=>'{"name":"Applicant","fafar_cf7crud_file_cv":["cv.pdf"]}','owner'=>'9','group_owner'=>'staff','permissions'=>'750'];
$wpdb->row=$row;
check(fafar_cf7crud_get_submission_by_id("x' OR 1=1 --")===false && $wpdb->queries===0,'SQL payload rejected before querying');
check(fafar_cf7crud_get_submission_by_id(['id'])===false,'array identifier rejected');
check(fafar_cf7crud_get_submission_by_id('../file')===false,'path traversal identifier rejected');
check(fafar_cf7crud_get_submission_by_id($row->id)===false,'anonymous cannot read private record');
$GLOBALS['user_id']=9;
check(fafar_cf7crud_get_submission_by_id($row->id)['name']==='Applicant','owner can read record');
check($wpdb->bindings[1]===[$row->id] && strpos($wpdb->bindings[0],'%s')!==false,'record query binds identifier');
check(fafar_cf7crud_get_submission_by_id($row->id,false,'write')===$row,'owner can update');
$GLOBALS['user_id']=10;
check(fafar_cf7crud_get_submission_by_id($row->id)===false,'unrelated user denied');
$GLOBALS['users'][10]->roles=['staff'];
check(fafar_cf7crud_get_submission_by_id($row->id,false)!==false,'group can read');
check(fafar_cf7crud_get_submission_by_id($row->id,false,'write')===false,'execute bit does not grant write');
$row->permissions='770';check(fafar_cf7crud_get_submission_by_id($row->id,false,'write')!==false,'group write bit permits update');
$GLOBALS['user_id']=0;$row->owner='0';$row->permissions='700';
check(fafar_cf7crud_get_submission_by_id($row->id)===false,'anonymous is never record owner zero');
$row->permissions='777';check(fafar_cf7crud_get_submission_by_id($row->id)!==false,'explicit public access preserved');
$row->permissions='bad';check(fafar_cf7crud_get_submission_by_id($row->id)===false,'invalid permission mask denied');
check(!fafar_cf7crud_check_write_permission(false),'missing record denied');
$wpdb->row=null;check(fafar_cf7crud_get_submission_by_id('missing')===false,'missing record handled');
$wpdb->row=$row;$row->permissions='777';$_GET['id']=$row->id;
check(fafar_cf7crud_get_submission_data_prop('name')===['Applicant'],'authorized prefill works');
check(fafar_cf7crud_get_file_attrs()===['fafar_cf7crud_file_cv'=>['cv.pdf']],'authorized attachment labels work');
$_GET['id']=['bad'];check(fafar_cf7crud_get_file_attrs()===[],'array query parameter safe');
$_GET['id']=$row->id;check(fafar_cf7crud_add_submission_id_hidden(['existing'=>'value'])===['existing'=>'value','fafar_cf7crud_submission_id'=>$row->id],'hidden fields preserved');
$legacy=clone $row;unset($legacy->permissions,$legacy->owner,$legacy->group_owner);$wpdb->row=$legacy;$legacy->form_id='8';
check(fafar_cf7crud_get_submission_by_id($row->id)===false,'legacy table respects private form policy');
$GLOBALS['user_id']=9;check(fafar_cf7crud_get_submission_by_id($row->id)!==false,'legacy table configured owner works');
$GLOBALS['user_id']=0;$legacy->form_id='7';check(fafar_cf7crud_get_submission_by_id($row->id)!==false,'legacy public form remains public');
$legacy->form_id='unknown';check(fafar_cf7crud_get_submission_by_id($row->id)===false,'orphan legacy record denied');
$tags=$GLOBALS['forms'][8]->scan_form_tags();
$record=fafar_cf7crud_insert_common_columns(['id'=>'original','form_id'=>8,'data'=>'{}'],['far_db_column_id'=>'attacker','far_db_column_permissions'=>'777','far_db_column_owner'=>'10'],$wpdb,'wp_fafar_cf7crud_submissions',$tags);
check($record['id']==='original' && $record['owner']==='9' && $record['permissions']==='700','client cannot replace identifier or form security policy');
$record=fafar_cf7crud_insert_common_columns(['id'=>'original','form_id'=>8,'data'=>'{}'],['far_db_column_permissions'=>'777','far_db_column_owner'=>'10'],$wpdb,'wp_fafar_cf7crud_submissions',$tags,true);
check(!isset($record['owner'])&&!isset($record['permissions']),'update cannot replace ownership or permissions');
$tmp=sys_get_temp_dir().'/fafar-security-'.bin2hex(random_bytes(5));mkdir($tmp);
try {
 foreach(["\x7fELFpayload","MZpayload","<?php system('id');","#!/bin/sh\n"] as $i=>$body){$source="$tmp/source$i.pdf";file_put_contents($source,$body);$blocked=false;try{fafar_cf7crud_process_uploaded_files(['cv'=>[$source]],'valid123',$tmp);}catch(RuntimeException $e){$blocked=true;}check($blocked,'executable upload blocked '.$i);}
 $source="$tmp/document.pdf";file_put_contents($source,'%PDF-1.4 example');$files=fafar_cf7crud_process_uploaded_files(['cv'=>[$source]],'valid123',$tmp);check(file_get_contents($tmp.'/'.$files['cv'][0])==='%PDF-1.4 example','normal PDF copy preserved');
} finally { foreach(glob($tmp.'/*') as $file)unlink($file);rmdir($tmp); }
$wpdb->row=$row;$row->owner='9';$row->permissions='700';
class TestSubmission {
 public $uploads_called=false;
 function get_posted_data($key=null) { return $key === 'fafar_cf7crud_create_submission' ? null : '1790000000abcdef1234'; }
 function uploaded_files() { $this->uploads_called=true;throw new RuntimeException('Uploads must not be reached'); }
}
$submission=new TestSubmission();$form=new TestForm();$GLOBALS['user_id']=0;
check(!fafar_cf7crud_before_send_mail_update($form,$submission) && !$submission->uploads_called,'unauthorized update rejected before uploads');
$abort=false;fafar_cf7crud_before_send_mail_handler($form,$abort,$submission);
check($abort===true && !$submission->uploads_called,'router abort is passed back to Contact Form 7');
$GLOBALS['user_id']=9;$row->form_id='99';
check(!fafar_cf7crud_before_send_mail_update($form,$submission) && !$submission->uploads_called,'cross-form update rejected before uploads');
$record=fafar_cf7crud_insert_common_columns(['form_id'=>8],[],$wpdb,'wp_fafar_cf7crud_submissions',$tags);
check($record['permissions']==='700' && $record['owner']==='9','omitted client security fields still use configured policy');
echo "All security regressions passed.\n";
