function PluginAccountForgot_v1(){
  this.restore_capture = function(){
    $('#frm_restore').hide();
    $('#restore_done').show();
  }
}
var PluginAccountForgot_v1 = new PluginAccountForgot_v1();
