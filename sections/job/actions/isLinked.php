<?php
    /**
     * \name		getSummary.php
     * \author    	Marc-Olivier Bazin-Maurice
     * \version		1.0
     * \date       	2018-06-05
     *
     * \brief 		Détermine si une job est liée à une Batch.
     * \details     Détermine si une job est liée à une Batch.
     */
    
    // Structure de retour vers javascript
    $responseArray = array("status" => null, "success" => array("data" => null), "failure" => array("message" => null));

    try
    {
        require_once $_SERVER["DOCUMENT_ROOT"] . "/Planificateur/lib/connect.php";
        require_once $_SERVER["DOCUMENT_ROOT"] . "/Planificateur/sections/job/model/job.php";
    
        // Initialize the session
        session_start();
                                                                                                    
        // Check if the user is logged in, if not then redirect him to login page
        if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
            if(!empty($_SERVER["HTTP_X_REQUESTED_WITH"]) && strtolower($_SERVER["HTTP_X_REQUESTED_WITH"]) == "xmlhttprequest")
            {
                throw new \Exception("You are not logged in.");
            }
            else
            {
                header("location: /Planificateur/lib/account/logIn.php");
            }
            exit;
        }
    
        // Getting a connection to the database.
        $db = new \FabPlanConnection();

        // Closing the session to let other scripts use it.
        session_write_close();
        
        // Vérification des paramètres
        $jobName = $_GET["name"] ?? null;
        $jobId = $_GET["id"] ?? null;
        
        $isLinked = null;
        try
        {
            $db->getConnection()->beginTransaction();
            
            $job = null;
            if(!empty($jobId))
            {
                // Get job by id
                $job = \Job::withID($db, $jobId);
                if($job === null)
                {
                    throw new \Exception("There is no job with the id \"{$jobId}\".");
                }
            }
            elseif(!empty($jobName))
            {
                // Get job by name
                $job = \Job::withName($db, $jobName);
                if($job === null)
                {
                    throw new \Exception("There is no job with the name \"{$jobName}\".");
                }
            }
            else
            {
                throw new \Exception("No job identifier provided.");
            }

            $isLinked = ($job->getParentBatch($db) !== null);

            $db->getConnection()->commit();
        }
        catch(\Exception $e)
        {
            $db->getConnection()->rollback();
            throw $e;
        }
        finally
        {
            $db = null;
        }

        // Retour au javascript
        $responseArray["status"] = "success";
        $responseArray["success"]["data"] = $isLinked;
    }
    catch(Exception $e)
    {
        $responseArray["status"] = "failure";
        $responseArray["failure"]["message"] = $e->getMessage();
    }
    finally
    {
        echo json_encode($responseArray);
    }
?>