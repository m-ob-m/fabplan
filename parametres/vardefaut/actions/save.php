<?php
    /**
     * \name		save.php
     * \author    	Marc-Olivier Bazin-Maurice
     * \version		1.0
     * \date       	2018-03-26
     *
     * \brief 		Sauvegarde la liste de paramètres d'un générique
     * \details 	Sauvegarde la liste de paramètres d'un générique
     */
    
    // Structure de retour vers javascript
    $responseArray = array("status" => null, "success" => array("data" => null), "failure" => array("message" => null));

    try
    {
        // INCLUDE
        require_once $_SERVER["DOCUMENT_ROOT"] . "/Planificateur/parametres/generic/controller/genericController.php";
        require_once $_SERVER["DOCUMENT_ROOT"] . "/Planificateur/lib/connect.php";

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
        
        // Vérification des paramètres
        $parameters = (isset($input->parameters) ? $input->parameters : null);
        $genericId = (isset($input->id) ? $input->id : null);
        
        $parametersArray = array();
        foreach($parameters as $parameter)
        {
            array_push(
                $parametersArray, 
                new \GenericParameter(
                    null, 
                    $genericId, 
                    $parameter->key, 
                    $parameter->value, 
                    $parameter->description, 
                    $parameter->quickEdit
                )
            );
        }
        
        findErrorsInParametersArray($parametersArray);
        
        try
        {
            $db->getConnection()->beginTransaction();
            $generic = \Generic::withID($db, $genericId, \MYSQLDatabaseLockingReadTypes::FOR_UPDATE);
            $generic->setParameters($parametersArray)->save($db, true);
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
        $responseArray["success"]["data"] = null;
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
     * Reports error in the parameters array
     *
     * @param array The array of parameters submitted by the user.
     *
     * @throws \Exception if there is a problem with the data
     * @author Marc-Olivier Bazin-Maurice
     * @return
     */ 
    function findErrorsInParametersArray(array $parameters) : void
    {
        $keyRegistry = array();
        foreach($parameters as $parameter)
        {
            if($parameter->getKey() === "" || $parameter->getKey() === null)
            {
                throw new \Exception("An empty key was found.");
            }
            elseif(!preg_match("/^[a-zA-Z_]\w{0,7}$/", $parameter->getKey()))
            {
                throw new \Exception("The key \"{$parameter->getKey()}\" is not valid.");
            }
            elseif(array_search($parameter->getKey(), $keyRegistry, true) !== false)
            {
                throw new \Exception("A duplicate of key \"{$parameter->getKey()}\" was found.");
            }
            else
            {
                array_push($keyRegistry, $parameter->getKey());
            }
        }
    }
?>