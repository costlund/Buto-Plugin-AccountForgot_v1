# Buto-Plugin-AccountForgot_v1

## Settings
```
plugin_modules:
  account_forgot:
    plugin: 'account/forgot_v1'
```
```
plugin:
  account:
    forgot_v1:
      enabled: true
      data:
        mysql: 'yml:/../buto_data/theme/_folder_/_folder_/mysql.yml'
        sql_account_select: |
          select 
          a.id,
          a.username,
          email.email,
          org.name
          from account as a
          inner join _table_where_email_is_    as email on a.id=email.account_id
          inner join _table_where_org_name_is_ as org on email.org_id=org.id
          where 
          not isnull(a.activated) and 
          email.email=?
          ;
        forgot_elements_above:
          -
            type: p
            innerHTML: Fill in your email and we send you details to restore.
        restore:
          url: '/d/restore'
```

## Widget
Insert widget in page file restore.yml
```
type: widget
data:
  plugin: account/forgot_v1
  method: restore
```


## Javascript
Open modal.
```
PluginWfBootstrapjs.modal({id: 'modal_account_forgot', url: '/account_forgot/forgot', lable: PluginI18nJson_v1.i18n('Forgot?'), size: 'sm'});
```

#Schema
```
/plugin/account/forgot_v1/mysql/schema.yml
```

# Usage

## Send method

Send restore password to only one account by account id.

```
wfPlugin::includeonce('account/forgot_v1');
$send_account = new PluginAccountForgot_v1();
$send_account->send('_account_id_');
```
