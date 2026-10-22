<?php

require_once 'BaseController.php';
require_once 'models/SyncModel.php';



class SyncController extends BaseController
{
    /**
     * Grab the write lock for the file & use as our own lock
     * across different apache threads, would probably work for 
     * other webserver frameworks as well
     */
    private function get_transaction_lock($file)
    {
        flock($file, LOCK_EX);
        return True;
    }
    
    /**
     * Release the held "transaction" lock from the file
     */
    private function release_transaction_lock($file)
    {
        flock($file, LOCK_UN);
    }

    public function indexAction()
    {
        $db = Globals::getDBConn();
        $json = Zend_Filter::filterStatic($this->getRequest()->getParam('json'), 'StripTags');

        // try catch to ensure we release the lock
        try {
            // sleep(60);
            // open lock file block if write lock is held by another transaction
            Globals::getLog()->info('OPENING LOCK FILE');

            $lock_file = fopen("/tmp/suma_transaction.lock", "w");
            $this->get_transaction_lock($lock_file);

            Globals::getLog()->info('GOT LOCK');


            $sync = new SyncModel();
            
            if ($sync->commit($json))
            {
                $this->view->result = true;
            }
            else
            {
                $this->view->result = false;
                $this->view->error = $sync->getError();
            }
    
        } 
        catch (\Throwable $th) 
        {
            Globals::getLog()->info('SYNC TRANSACTION ERROR - ' . $th->getMessage());
        } 
        finally
        {
            $this->release_transaction_lock($lock_file);
            Globals::getLog()->info('RELEASED LOCK');
        }
    }
}

