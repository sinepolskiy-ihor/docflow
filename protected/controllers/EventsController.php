<?php

class EventsController extends Controller {

  /**
   * @var string the default layout for the views. Defaults to '//layouts/column2', meaning
   * using two-column layout. See 'protected/views/layouts/column2.php'.
   */
  public $layout = '//layouts/docflow';
  public $defaultAction = 'admin';
  public $wdays = array("нд","пн","вт","ср","чт","пт","сб","нд");
  public $wday_alias = array(
     "нд" => "щонеділі",
     "пн" => "щопонеділка",
     "вт" => "щовівторка",
     "ср" => "щосереди",
     "чт" => "щочетверга",
     "пт" => "щоп`ятниці",
     "сб" => "щосуботи",
  );
  /**
   * @return array action filters
   */
  public function filters() {
    return array(
        'accessControl', // perform access control for CRUD operations
        //'postOnly + delete', // we only allow deletion via POST request
    );
  }

  /**
   * Specifies the access control rules.
   * This method is used by the 'accessControl' filter.
   * @return array access control rules
   */
  public function accessRules() {
    return array(
        array('deny', // deny all anonymous users
            'users' => array('?'),
        ),
        array('allow', //
            'actions' => array('create', 
              'xupdate', 'update'),
            'roles' => array('Event'),
        ),
        array('allow', //
            'actions' => array('eventdatedelete', 'delete'),
            'roles' => array('Event'),
        ),
        array('allow', //
            'actions' => array('index','admin'),
            'users' => array('@'),
        ),
        array('deny', // deny all users
            //'actions'=>array('index'),
            'users' => array('*'),
        ),
    );
  }

  /**
   * Creates a new model.
   * If creation is successful, the browser will be redirected to the 'view' page.
   */
  public function actionCreate() {
    $model = new Events;
    $nmodel = $this->commonSave($model);
    $this->render('create', array(
        'model' => $nmodel,
    ));
  }
  

  public function actionXupdate(){
    $reqField = Yii::app()->request->getParam('field',null);
    $modelName = 'Events';
    $es = new EditableSaver($modelName);
    $es->update();
  }
  
  public function actionUpdate($id){
    $model = $this->loadModel($id);
    $nmodel = $this->commonSave($model);
    $this->render('update', array(
        'model' => $nmodel,
    ));
  }
  
  protected function commonSave($model){
    $Events = Yii::app()->request->getParam('Events',array());
    $eventdates = Yii::app()->request->getParam('eventdates',array());
    $invited_ids = Yii::app()->request->getParam('invited_ids',array());
    $invited_descrs = Yii::app()->request->getParam('invited_descrs',array());
    $invited_seets = Yii::app()->request->getParam('invited_descrs_comment',array());
    $organizer_ids = Yii::app()->request->getParam('organizer_ids',array());
    $organizer_descrs = Yii::app()->request->getParam('organizer_descrs',array());
    if (!empty($Events)) {
      $model->attributes = $Events;
      $model->invited_ids = $invited_ids;
      $model->invited_descrs = $invited_descrs;
      $model->invited_seets = $invited_seets;
      $model->organizer_ids = $organizer_ids;
      $model->organizer_descrs = $organizer_descrs;
      $model->event_dates = $eventdates;
      if (!$model->FinishTime){
        $model->FinishTime = null;
      }
      if (!$model->StartTime){
        $model->StartTime = null;
      }
      if (empty($model->event_dates)){
        throw new CHttpException(400, 
          'Помилка збереження заходу : невірно вказана дата');
      }
      $model->UserID = Yii::app()->user->id;
      if ($model->save()){
        $resp = 'Не вдалося оновити на сайті ЗНУ';
        $response = $this->SendToService($model);
        //var_dump($response);exit();
        $decoded_response = json_decode($response);

        if (isset($decoded_response->calendar)){
          $resp = $decoded_response->calendar->id;
          $model->ExternalID = $decoded_response->calendar->id;
          $model->NewsUrl = 
          str_replace('{year}', date("Y",strtotime($model->event_dates[0])), 
            str_replace('{month}', date("m",strtotime($model->event_dates[0])),
              str_replace('{day}', date("d",strtotime($model->event_dates[0])),
                $decoded_response->calendar->url)));
          $model->save();
        }
        $this->redirect(Yii::app()->CreateUrl('events/index',array('id' => $model->idEvent,
           'response' => $resp)));
      }
    }
    return $model;
  }

  /**
   * Deletes a particular model.
   * If deletion is successful, the browser will be redirected to the 'admin' page.
   * @param integer $id the ID of the model to be deleted
   */
  public function actionDelete($id) {
    $model = $this->loadModel($id);

    $model->delete();
    // if AJAX request (triggered by deletion via admin grid view), we should not redirect the browser
    if (!isset($_GET['ajax']))
      $this->redirect(isset($_POST['returnUrl']) ? $_POST['returnUrl'] : array('admin'));
  }

  /**
   * Видалення однієї з дат заходу (якщо це остання, то і самого заходу)
   * @param integer $id the ID of the model to be deleted
   */
  public function actionEventdatedelete($id) {
    $model = Eventdates::model()->findByPk($id);
    if ($model){
      $model->delete();
    } else {
      $id = 0;
    }
    // if AJAX request (triggered by deletion via admin grid view), we should not redirect the browser
    if (!isset($_GET['ajax']))
      $this->redirect(isset($_POST['returnUrl']) ? $_POST['returnUrl'] : array('admin'));
  }

  /**
   * View separate event.
   */
  public function actionIndex($id,$response = '') {
    $model = $this->loadModel($id);
    $this->render('index', array(
        'model' => $model,
        'response' => $response,
    ));
  }

  /**
   * Manages all models.
   */
  public function actionAdmin() {
    $model = new Events('search');
    $model->unsetAttributes();  // clear any default values
    if (isset($_GET['Events'])){
      $model->attributes = $_GET['Events'];
      if (isset($_GET['Events']['past'])){
        $model->past = $_GET['Events']['past'];
      } else {
        $model->past = 0;
      }
    }
    $this->render('admin', array(
        'model' => $model,
    ));
  }

  /**
   * Returns the data model based on the primary key given in the GET variable.
   * If the data model is not found, an HTTP exception will be raised.
   * @param integer the ID of the model to be loaded
   */
  public function loadModel($id) {
    $model = Events::model()->findByPk($id);
    if ($model === null)
      throw new CHttpException(404, 'The requested page does not exist.');
    return $model;
  }

  /**
   * Performs the AJAX validation.
   * @param CModel the model to be validated
   */
  protected function performAjaxValidation($model) {
    if (isset($_POST['ajax']) && $_POST['ajax'] === 'events-form') {
      echo CActiveForm::validate($model);
      Yii::app()->end();
    }
  }
  
  /**
   * Відправлення даних на веб-сервіс через CURL POST-запитом
   * @param Events $model
   */
  protected function SendToService($model){
    $response = false;
    $date_intervals = array();
    // підключення
    //$url = "http://sites.znu.edu.ua/cms/index.php";
    $url = "http://10.1.22.8/cms/index.php"; //test-service
    $ch = curl_init($url);
    $invited = "";
    $organizers = "";
    $date_time =  preg_replace("/,(\d\d?)(,|$)/i",",$1 числа кожного місяця$2",
          str_replace($this->wdays,$this->wday_alias, mb_strtolower($model->DateSmartField,'utf8')))
        . " ".(($model->StartTime)? mb_substr($model->StartTime,0,5,"utf-8"): "(час початку не вказано)")
        .(($model->FinishTime)? " - ".mb_substr($model->FinishTime,0,5,"utf-8"): "")
;
    $vals = $model->getInvited(); 
    for ($i = 0; ($i < count($vals) && is_array($vals)); $i++){
      if ($i == 0){
        $invited .= '<ul>';
      }
      $invited .= '<li>'.$vals[$i]['InvitedComment']
        .'</li>';
      if ($i == count($vals) - 1){
        $invited .= "</ul>";
      }
    }
    $vals = $model->getOrganizers(); 
    for ($i = 0; ($i < count($vals) && is_array($vals)); $i++){
      if ($i == 0){
        $organizers .= '<ul>';
      }
      $organizers .= '<li>'.$vals[$i]['OrganizerComment']
        .'</li>';
      if ($i == count($vals) - 1){
        $organizers .= "</ul>";
      }
    }
    
    for ($i = 0; $i < count($model->event_dates) && is_array($model->event_dates); $i++){
      $begin_timestamp = strtotime($model->event_dates[$i] . ' ' . $model->StartTime);
      $end_timestamp = strtotime($model->event_dates[$i] . ' ' . $model->FinishTime);
      $date_intervals[] = array(
         'pochrik' => date('Y',$begin_timestamp),
         'pochmis' => date('m',$begin_timestamp),
         'pochtyzh' => -1,
         'pochday' => date('d',$begin_timestamp),
         'pochgod' => date('H',$begin_timestamp),
         'pochhv' => date('i',$begin_timestamp),
         
         'kinrik' => date('Y',$end_timestamp),
         'kinmis' => date('m',$end_timestamp),
         'kintyzh' => -1,
         'kinday' => date('d',$end_timestamp),
         'kingod' => date('H',$end_timestamp),
         'kinhv' => date('i',$end_timestamp)
      );
    }
    // дані для відправки
    $data = array(
      //'api_key' => 'dksjf;aj;weio[wlooiuoiuhlk;lk\'',
      'api_key' => '1234567',//test service
      'action' => 'calendar/api/'.(($model->ExternalID)? 'update':'create'),
      'lang' => 'ukr',
      'site_id' => 62,//89,//62,
      'nazva' => $model->EventName,
      'vis' => 1,
      'categories' => implode(',',
        array(
          $model->eventKind->EventKindName,
          $model->eventType->EventTypeName
        )
      ),
      'dates' => $date_intervals, 
      'description' => ''
        . '<div class="EventPlaceHeader">Місце проведення: </div>'
        . '<div class="EventPlace">'
            .((empty($model->EventPlace))? 
            "не вказано":$model->EventPlace) . '</div>'
        . '<div class="DateTimeHeader">Дата і час: </div> '
        . '<div class="DateTime">'.$date_time . '</div>'
        . '<div class="EventDescription">'.$model->EventDescription . '<div/>'
        . '<div class="InvitedHeader">Запрошені: </div>'
        . '<div class="InvitedList">'.((empty($invited))? "не вказано":$invited).'</div>'
        . '<div class="OrganizersHeader">Організатори: </div>'
        . '<div class="OrganizersList">'.((empty($organizers))? "не вказано":$organizers).'</div>'
        . '<div class="ResponsibleHeader">'.'Відповідальні особи: </div>'
        . '<div class="Responsible">'
            .((empty($model->Responsible))? 
            "не вказано":$model->Responsible) . '</div>'
        . '<div class="ContactsHeader">'.'Контактні дані: </div>'
        . '<div class="Contacts">'
            .((empty($model->ResponsibleContacts))? 
            "не вказано":$model->ResponsibleContacts) . '</div>'
    );
    if ($model->ExternalID > 0){
      $data['id'] = $model->ExternalID;
    }
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    // треба отримати результат
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    // запит...
    $response = curl_exec($ch);
    //$errmsg  = curl_error( $ch );
    //$err     = curl_errno( $ch );
    //$header  = curl_getinfo( $ch );
    // закрити з_єднання
    //curl_close($ch);
    return $response;
  }
  
}
