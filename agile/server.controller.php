<?php
class ServerController extends BaseController {
    /**
     * events that we can send to the clients
     * Thermometer : ID 1
     * Button : ID 2
     * Slider : ID 3
     */
    protected $eventTypes = array(
        'Thermometer',
        'Button',
        'Slider',
    );
    
    protected $eventTexts = array(
        array(
            'Change the',
            'Rotate the',
            'Move the'
        ),
        array(
            'Turn on',
            'Press',
            'Combobulate',
        ),
        array(
            'Change the',
            'Slide the',
            'Move the'
        ),
    );
    
    protected $eventMaxValues = array(
        100,
        1,
        5,
    );
    
    protected $pusher;

    /**
     * run initial game setup
     */
    public function createGame() {
        $gameModel = new Game();
        $gameModel->createGame();
    }

    /**
     * Stop the current game
     * @return [type] [description]
     */
    public function stopGame() {
        $gameModel = new Game();
        $gameModel->stopGame(Game::getCurrentGame());
    }

    public function restartGame() {
        $gameModel = new Game();
        $gameModel->restartGame();
    }
    
    public function getGame() {
        return Game::getCurrentGame();
    }
    
    /**
     * save this user into this game
     * probably need to pass in user and game ids
     */
    public function createUser($user_id) {
        $userGameModel = new UserGame();
        return $userGameModel->setUserGame($user_id,Game::getCurrentGame());
    }
    
    /**
     * get all users for this game
     */
    public function getUsers($game_id=false) {
        return UserGame::getGameUsers($game_id);
    }
    
    public function getTickerText($event) {
        $values = $this->eventTexts[$event->getEventType()-1];
        $rand   = rand(0, count($values)-1);
        $text   = '';
        
        switch($event->getEventType()) {
            case 2:
                $text = "{$values[$rand]} the {$event->name}";
                break;
            case 1:
            case 3:
            default:
                $text = "{$values[$rand]} {$event->name} to {$event->success}";
                break;
        }
        
        return $text;
    }

    /**
     * create a random event assigned to a user
     */
    public function createEvent() {
        
        // get random user from game
        $user   = User::orderBy(DB::raw('RAND()'))->first();
        $event  = $this->getRandomEvent($user->id);
        
        // get user to show this event to
        $showTo = DB::table("users")->select("id")->where('id', '<>', $user->id)->orderBy(DB::raw('RAND()'))->first();
        $push   = array(
            'event_id'   => $event->id,
            'user_id'    => $event->getUserId(),
            'control_id' => $event->getEventType(),
            'show_to'    => $showTo->id,
            'show_text'  => $this->getTickerText($event),
            'cid' 		 => $event->control_id             
        );
        
        // push to client and display
        $this->getPusher()->trigger(
            Config::get('app.pusher_channel_name'), 
            'event_create', 
            $push
        );
    }
    
    /**
     * used to decide if updated value is correct for event
     * NOTE - value cant be zero, comes through as null
     */
    public function checkEvent() {
        $user_id    = Route::input('user_id');
        $control_id = Route::input('control_id');
        $value      = Route::input('value');
        $cid        = Route::input('cid');
        
        if(empty($value)) {
            $value = "0";
        }
        
        // run check for this user and this control
        if($event_id = BaseEvent::checkEvent($cid, $control_id, $value)) {
            // TODO - success needs to mark event as completed
            $stat = array(
                ""    
            );
            $prev = DB::table("user_stats")->where('user_id', '=', $user_id)->where('name', '=', 'Completed Events')->first();
            
            if(!$prev){
                
                $stat = array();
                $stat['name'] = 'Completed Events';
                $stat['user_id'] = $user_id;
                $stat['value'] = 1;
                DB::table("user_stats")->insertGetId($stat);
            }else{
                $prev->value++;
                DB::table('user_stats')->where('id', $prev->id)->update(array('value' => $prev->value));   
                $this->checkAchievement($user_id, 'Completed Events', $prev->value);
            }
            
            DB::table("events")->where("id", $event_id)->delete();
            
            // if success needs to tell main screen
            $this->getPusher()->trigger(
                Config::get('app.pusher_channel_name'), 
                'event_success', 
                $event_id
             );
        }
    }
    
    
    /**
     * call this when an event isn't completed
     * triggered from client side
     */
    public function failEvent() {
        $user_id    = Route::input('user_id');
        $event_id = Route::input('event_id');
        
        // this needs to update the event db
        $gameModel = new Game();
        $gameModel->removeHealth(Game::getCurrentGame(),10);

        DB::table("events")->where("id", $event_id)->delete();
    }
    
    public function checkAchievement($user_id, $name, $value){
        if(in_array($value, array(1, 5, 10, 25, 50))){
            $achname = $name.' - '.$value;
            $achieve = DB::table("achievements")->where('name', '=', $achname)->first();
            
            if(!$achieve){
                $newachievement = array(
                    "name" => $achname
                );
                DB::table("achievements")->insertGetId($newachievement);
                $achieve = DB::table("achievements")->where('name', '=', $achname)->first();
            }
            
            $userach = array(
                "user_id" => $user_id,
                "achievement_id" => $achieve->id
            );
            DB::table("achievements_users")->insertGetId($userach);
            
        }
    }
    
    /**
     * create 3 random controls for the user
     */
    public function createUserControls($user_id) {
        $controls = array();
        
        for($i=0; $i<3; $i++) {
            if($control = $this->getRandomControls($user_id)) {
                $controls[] = $control;
            }
        }
        
        return $controls;
    }
    
    public function forceClientEnd() {
        $this->getPusher()->trigger(
            Config::get('app.pusher_channel_name'), 
            'event_clientEnd', 
            "1"
        );
    }
    
    /**
     * create the pusher connection
     */
    protected function setupPusher() {
        $this->pusher = new Pusher(
            Config::get('app.pusher_app_key'), 
            Config::get('app.pusher_app_secret'), 
            Config::get('app.pusher_app_id')
        );
    }
    
    protected function getPusher() {
        if(empty($this->pusher)) {
            $this->setupPusher();
        }
        
        return $this->pusher;
    }
    
    /**
     * function to call when an event is completed
     */
    protected function completeEvent() {
        // mark an event as completed
    }
    
    /**
     * get a random event
     */
    protected function getEventType() {
        return $this->eventTypes[rand(0, count($this->eventTypes) -1)];
    }
    
    /**
     * create and return a random event assigned to a user
     */
    protected function getRandomEvent($user_id=false) {
        $event = false;
        
        if(!empty($user_id)) {
            // get random control based on user
            // create event with random value if applicable
            $control        = BaseControl::getRandomUserControl($user_id);
            $control_type   = (int)$control->type_id;
            $success_val    = rand(0, $this->eventMaxValues[$control_type-1]);
            $event          = new BaseEvent(false, $user_id, $control_type, $control->name, $success_val, $control->id);
        }
        
        return $event;
    }
    
    /*
     * get a random controller
     */
    protected function getRandomControls($user_id=false) {
        $control = false;
        
        if(!empty($user_id)) {
            $type        = $this->getEventType();
            $controlName = "{$type}Control";
            $control     = new $controlName($user_id);
        }
        
        return $control;
    }

    public function getHealth(){
        $gameModel = new Game();
        $health = $gameModel->getHealth(Game::getCurrentGame());
        echo json_encode(
                array(
                    'alive' => ($health>0)?true:false,
                    'health' => $health
                    )
            );
    }

    public function replayGame() {
        $gameModel = new Game();
        if(!Game::getCurrentGame()) {
            $gameModel->createGame();
        }
        return Redirect::to("social");
    }

    public function endGame() {
        $gameModel = new Game();
        $gameModel->restartGame();
        return Redirect::to("front");
    }
}