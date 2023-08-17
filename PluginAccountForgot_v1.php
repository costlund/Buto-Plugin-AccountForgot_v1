<?php
class PluginAccountForgot_v1{
  private $settings = null;
  public $mysql;
  private $i18n = null;
  function __construct() {
    wfPlugin::includeonce('wf/yml');
    wfPlugin::includeonce('wf/mysql');
    $this->settings = wfPlugin::getPluginSettings('account/forgot_v1', true);
    $this->mysql =new PluginWfMysql();
    wfPlugin::includeonce('i18n/translate_v1');
    $this->i18n = new PluginI18nTranslate_v1();
    $this->i18n->path = '/plugin/account/forgot_v1/i18n';
  }
  /**
   * FORGOT
   */
  public function page_forgot(){
    $form = $this->getForm('forgot');
    $form->setByTag($this->settings->get('data'), 'data');
    $widget = wfDocument::createWidget('form/form_v1', 'render', $form->get());
    wfDocument::renderElement(array($widget));
  }
  public function page_capture(){
    $form = $this->getForm('forgot');
    $widget = wfDocument::createWidget('form/form_v1', 'capture', $form->get());
    wfDocument::renderElement(array($widget));
  }
  public function capture(){
    $data = $this->send();
    $alert = $this->i18n->translateFromTheme('Message was sent!');
    return array("alert('$alert');location.reload()");
  }
  public function send($account_id = null){
    $account = $this->db_account_select($account_id);
    if(sizeof($account)>0){
      wfPlugin::includeonce('mail/queue');
      $mail = new PluginMailQueue(true);
      $subject = $this->i18n->translateFromTheme('Restore password').' - '.wfServer::getHttpHost();
      $body = $this->body_get($account);
      $mail->send($subject, $body, wfRequest::get('email'), null, null, null, null, wfUser::getSession()->get('user_id'), 'account_forgot');
    }
    return $account;
  }
  private function body_get($account){
    $body = $this->getElement('body');
    $element = array();
    foreach ($account as $key => $value) {
      $id = wfCrypt::getUid();
      $item = new PluginWfArray($value);
      $url = wfServer::calcUrl().$this->settings->get('data/restore/url').'/id/'.$id;
      $item->set('url', $url);
      /**
       * Body item.
       */
      $body_item = $this->getElement('body_item');
      $body_item->setByTag($item->get());
      /**
       * Merge element.
       */
      $element = array_merge($element, $body_item->get());
      /**
       * Db.
       */
      $this->db_account_forgot_insert(array('id' => $id, 'account_id' => $item->get('id'), 'session_id' => session_id()));
    }
    $body->setByTag(array('element' => $element));
    $body->setByTag(wfServer::get());
    /**
     * Render element and get content.
     */
    wfDocument::$capture=2;
    wfDocument::renderElement($body->get());
    $content = wfDocument::getContent();
    return $content;
  }
  public function getElement($key, $dir = __DIR__){
    return new PluginWfYml($dir."/element/$key.yml");
  }
  public function getForm($name, $dir = __DIR__){
    return new PluginWfYml($dir.'/form/'.$name.'.yml');
  }
  /**
   * RESTORE
   */
  public function widget_restore(){
    $rs = $this->db_account_forgot_select_one(wfRequest::get('id'));
    if($rs->get('id')){
      /**
       * Details
       */
      $element = $this->getElement('restore_details');
      $element->setByTag($rs->get());
      wfDocument::renderElement($element->get());
      /**
       * Form
       */
      $form = $this->getForm('restore');
      $widget = wfDocument::createWidget('form/form_v1', 'render', $form->get());
      wfDocument::renderElement(array($widget));
      /**
       * Script
       */
      $element = $this->getElement('script');
      wfDocument::renderElement($element->get());
      /**
       * Done (hidden)
       */
      $element = $this->getElement('restore_done');
      wfDocument::renderElement($element->get());
    }else{
      $element = $this->getElement('restore_invalid');
      wfDocument::renderElement($element->get());
    }
  }
  public function page_restore_capture(){
    $form = $this->getForm('restore');
    $widget = wfDocument::createWidget('form/form_v1', 'capture', $form->get());
    wfDocument::renderElement(array($widget));
  }
  public function validate_password($field, $form, $data = array()){
    $form = new PluginWfArray($form);
    if($form->get("items/$field/is_valid")){
      if(wfRequest::get('password')!= wfRequest::get('password2')){
        $form->set("items/$field/is_valid", false);
        $form->set("items/$field/errors/", $this->i18n->translateFromTheme('Passwords is not equal!'));
      }
    }
    return $form->get();
  }
  public function restore_capture(){
    $rs = $this->db_account_forgot_select_one(wfRequest::get('id'));
    if($rs->get('id')){
      $rs->set('password', wfCrypt::getHashAndSaltAsString(wfRequest::get('password')));
      $this->db_account_update_password($rs->get());
      $this->db_account_forgot_update_success($rs->get());
    }
    return array("PluginAccountForgot_v1.restore_capture()");
  }
  /**
   * DB
   */
  public function db_open(){
    $this->mysql->open($this->settings->get('data/mysql'));
  }
  public function getSql($key, $dir = __DIR__){
    $sql = new PluginWfYml($dir.'/mysql/sql.yml', $key);
    /**
     * Replace.
     * If [replace._any_key] exist in sql we replace from param replace._any_key. 
     */
    if(wfPhpfunc::strstr($sql->get('sql'), '[replace.')){
      $replace = new PluginWfYml($dir.'/mysql/sql.yml', 'replace');
      foreach ($replace->get() as $key => $value) {
        $sql->set('sql', wfPhpfunc::str_replace("[replace.$key]", $value, $sql->get('sql')));
      }
    }
    return $sql;
  }
  public function db_account_select($account_id = null){
    $this->db_open();
    $sql = $this->getSql('account_select');
    /**
     * Change sql from theme settings.
     */
    $sql->set('sql', $this->settings->get('data/sql_account_select'));
    /**
     * 
     */
    $this->mysql->execute($sql->get());
    $rs = $this->mysql->getStmtAsArray();
    /**
     * Remove other account if account_id is set.
     */
    if($account_id){
      foreach ($rs as $key => $value) {
        $i = new PluginWfArray($value);
        if($value['id']!=$account_id){
          unset($rs[$key]);
        }
      }
    }
    return $rs;
  }
  public function db_account_forgot_insert($data){
    $this->db_open();
    $sql = $this->getSql('account_forgot_insert', __DIR__);
    $sql->setByTag($data);
    $this->mysql->execute($sql->get());
    return null;
  }
  public function db_account_forgot_select_one($id){
    $this->db_open();
    $sql = $this->getSql('account_forgot_select_one');
    $sql->setByTag(array('id' => $id));
    $this->mysql->execute($sql->get());
    $rs = new PluginWfArray($this->mysql->getStmtAsArrayOne());
    return $rs;
  }
  public function db_account_update_password($data){
    $this->db_open();
    $sql = $this->getSql('account_update_password', __DIR__);
    $sql->setByTag($data);
    $this->mysql->execute($sql->get());
    return null;
  }
  public function db_account_forgot_update_success($data){
    $this->db_open();
    $sql = $this->getSql('account_forgot_update_success', __DIR__);
    $sql->setByTag($data);
    $this->mysql->execute($sql->get());
    return null;
  }
}