<?php
    /**
     * \name		save.php
     * \author    	Marc-Olivier Bazin-Maurice
     * \version		1.0
     * \date       	2019-02-12
     *
     * \brief 		Saves a batch.
     * \details     Saves a batch.
     */
    
    // Structure de retour vers javascript
    $responseArray = array("status" => null, "success" => array("data" => null), "failure" => array("message" => null));

    try
    {
        // INCLUDE
        require_once $_SERVER["DOCUMENT_ROOT"] . "/Planificateur/lib/connect.php";
        require_once $_SERVER["DOCUMENT_ROOT"] . "/Planificateur/sections/job/controller/jobController.php";
        require_once $_SERVER["DOCUMENT_ROOT"] . "/Planificateur/parametres/generic/controller/genericController.php";
        require_once $_SERVER["DOCUMENT_ROOT"] . "/Planificateur/parametres/model/controller/modelController.php";
        require_once $_SERVER["DOCUMENT_ROOT"] . "/Planificateur/parametres/type/controller/typeController.php";

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

        $inputJob =  json_decode(file_get_contents("php://input"));
        
        try
        {
            $db->getConnection()->beginTransaction();
            $job = buildJob($db, $inputJob)->save($db);

            $batch = $job->getParentBatch($db, \MYSQLDatabaseLockingReadTypes::FOR_UPDATE);
            if($batch !== null)
            {
                $batch->setMprStatus("N")->updateCarrousel()->save($db);
            }

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
        $responseArray["success"]["data"] = $job->getId();
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

    /**
     * Builds a job from a javascript object.
     *
     * @param \FabplanConnection $db The database to query.
     * @param \stdClass $inputJob The javascript object input job.
     *
     * @throws \Exception if there is an error.
     * @author Marc-Olivier Bazin-Maurice
     * @return \Job A Job object.
     */
    function buildJob(\FabPlanConnection $db, \stdClass $inputJob) : \Job
    {
        $jobTypes = array();
        if(!empty($inputJob->jobTypes))
        {
            foreach($inputJob->jobTypes as $inputJobType)
            {
                $model = \Model::withID($db, $inputJobType->model->id);
                if($model === null)
                {
                    throw new \Exception("Il n'y a pas de modèle avec l'identifiant unique \"{$inputJobType->model->id}\".");
                }
                
                $type = \Type::withImportNo($db, $inputJobType->type->importNo);
                if($type === null)
                {
                    throw new \Exception("Il n'y a pas de type avec le numéro d'importation \"{$inputJobType->type->importNo}\".");
                }
                
                $generic = $type->getGeneric();
                
                $parts = array();
                if(!empty($inputJobType->parts))
                {
                    foreach($inputJobType->parts as $inputPart)
                    {
                        $part = new \JobTypePorte(is_string($inputPart->id) ? intval($inputPart->id) : $inputPart->id, 
                            is_string($inputJobType->id) ? intval($inputJobType->id) : $inputJobType->id, $inputPart->quantity, 0, 
                            $inputPart->length, $inputPart->width, $inputPart->grain, $inputPart->done ?? "N", null);
                        array_push($parts, $part);
                    }
                }
                
                $parameters = array();
                $mprFileName= null;
                $mprFileContents = null;
                if($model->getId() !== 2)
                {
                    foreach($generic->getParameters() as $genericParameter)
                    {
                        $key = $genericParameter->getKey();
                        $genericValue = $genericParameter->getValue();
                        $value = $inputJobType->parameters->$key;
                        if($value !== $genericValue && $value !== null && $value !== "")
                        {
                            $parameter = new \JobTypeParameter(is_string($inputJobType->id) ? intval($inputJobType->id) : $inputJobType->id, 
                                $key, $value);
                            array_push($parameters, $parameter);
                        }
                    }
                }
                else
                {
                    $mprFileName = $inputJobType->mprFileName;
                    $mprFileContents = $inputJobType->mprFileContents;
                }
                
                $jobType = new \JobType(is_string($inputJobType->id) ? intval($inputJobType->id) : $inputJobType->id, $inputJob->id, 
                    $model, $type, $mprFileName, $mprFileContents, null, $parameters, $parts);
                array_push($jobTypes, $jobType);
            }
        }
        
        $job = \Job::withID($db, $inputJob->id, MYSQLDatabaseLockingReadTypes::FOR_UPDATE);
        if($job === null)
        {
            throw new \Exception("La création de job n'a pas encore été implémentée.");
        }
        return $job
            ->setName($inputJob->name ?? $job->getName())
            ->setDeliveryDate($inputJob->deliveryDate ?? $job->getDeliveryDate())
            ->setJobTypes($jobTypes);
    }
?>