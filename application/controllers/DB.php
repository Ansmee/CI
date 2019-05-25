<?php

class DB extends MY_Controller
{
    public function init($model = '')
    {
        try {
            if ($model) {
                $myModel = $this->load->model($model);
                if (!$myModel) throw new \Exception();

                $bool = $this->$model->initTable();
            } else {
            }
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }
    }
}
