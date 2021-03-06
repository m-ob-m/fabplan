<?php
    /**
     * \name		save.php
     * \author    	Marc-Olivier Bazin-Maurice
     * \version		1.0
     * \date       	2018-04-18
     *
     * \brief 		Sauvegarde un Material
     * \details     Sauvegarde un Material
     */
    
    // Structure de retour vers javascript
    $responseArray = array("status" => null, "success" => array("data" => null), "failure" => array("message" => null));
    
    try
    {
        // INCLUDE
        require_once $_SERVER["DOCUMENT_ROOT"] . "/Planificateur/lib/connect.php";
        require_once $_SERVER["DOCUMENT_ROOT"] . "/Planificateur/parametres/type/controller/typeController.php";
        require_once $_SERVER["DOCUMENT_ROOT"] . "/Planificateur/lib/numberFunctions/numberFunctions.php";

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

        $input =  json_decode(file_get_contents("php://input"));
        
        $id = (isset($input->id) ? intval($input->id) : null);
        $importNo = (isset($input->importNo) ? intval($input->importNo) : null);
        $description = (isset($input->description) ? $input->description : null);
        $genericId = (isset($input->genericId) ? $input->genericId : null);
        $copyParametersFrom = null;
        if(is_positive_integer_or_equivalent_string($input->copyParametersFrom ?? null, true, true))
        {
            $copyParametersFrom = intval($input->copyParametersFrom);
        }
        

        try
        {
            $db->getConnection()->beginTransaction();
            $type = new \Type();
            if($id !== null)
            {
                $type = \Type::withID($db, $id, \MYSQLDatabaseLockingReadTypes::FOR_UPDATE);
                if($type === null)
                {
                    throw new \Exception(
                        "Il n'y a aucun type possédant l'identifiant numérique unique \"{$id}\"."
                    );
                }
            }
            
            $generic = \Generic::withID($db, $genericId);
            $type->setImportNo($importNo)->setDescription($description)->setGeneric($generic);
            $type = saveType($type, $copyParametersFrom, $db);
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
        $responseArray["success"]["data"] = $type->getId();
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
     * Saves the Type
     *
     * @param \Type $type The Type to save
     * @param int $referenceId The id of the Type to copy parameters from
     * @param \FabplanConnection $db The database where the record should be created or updated.
     *
     * @throws
     * @author Marc-Olivier Bazin-Maurice
     * @return \Type The saved Type
     */
    function saveType(\Type $type, ?int $referenceId = null, \FabplanConnection $db) : \Type
    {
        $create = ($type->getId() === null) ? true : false;
        $type->save($db);
        if($create && $referenceId !== null)
        {
            $firstParameter = true;
            
            $query = "INSERT INTO `door_model_data` (`fkDoorModel`, `fkDoorType`, `paramKey`, `paramValue`) VALUES ";
            /* @var $parameter \ModelTypeParameter */
            foreach(\Type::withId($db, $referenceId)->getModelTypeParametersForAllModels($db) as $parameter)
            {
                if(!$firstParameter)
                {
                    $query .= ", ";
                }
                $firstParameter = false;

                $modelId = $db->getConnection()->quote($parameter->getModelId());
                $typeNo = $db->getConnection()->quote($type->getImportNo());
                $key = $db->getConnection()->quote($parameter->getKey());
                $value = $db->getConnection()->quote($parameter->getValue());
                $query .= "({$modelId}, {$typeNo}, {$key}, {$value})";
            }
            $query .= ";";
            
            $stmt = $db->getConnection()->prepare($query);
            $stmt->execute();
        }
            
        return $type;
    }
?>