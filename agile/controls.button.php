<?php 
class ButtonControl extends BaseControl {
    
    public function createControl() {
        // make a random name
        $this->getName();
        // make a random value
        $this->randomValue();
        $this->controlType = 2;
        // save to db
    }
    
    public function randomValue() {
        $this->value = $this->getCatchPhrase();
    }
}
?>