<?php

class DB extends MY_Controller
{
    public function init($model = '')
    {
        try {
            if ($model) {
                echo "{$model} \n";

                $myModel = $this->load->model($model);
                if (!$myModel) throw new \Exception();

                $bool = $this->$model->initTable();

                if ($bool) echo "Completed \n";
            } else {
            }
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }
    }
}
