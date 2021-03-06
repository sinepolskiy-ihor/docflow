<h1>Загальний чат</h1>
<div id='chat' style='background-color: lightblue;'></div>
<?php 
    $this->widget('YiiChatWidget',array(
        'chat_id'=>'123',                   // a chat identificator
        'identity'=>Yii::app()->user->id,                      // the user, Yii::app()->user->id ?
        'selector'=>'#chat',                // were it will be inserted
        'minPostLen'=>2,                    // min and
        'maxPostLen'=>1000,                   // max string size for post
        'model'=>new ChatHandler(),    // the class handler. **** FOR DEMO, READ MORE LATER IN THIS DOC ****
        'data'=>'any data',                 // data passed to the handler
        // success and error handlers, both optionals.
        'onSuccess'=>new CJavaScriptExpression(
            "function(code, text, post_id){   }"),
        'onError'=>new CJavaScriptExpression(
            "function(errorcode, info){  }"),
    ));
?>