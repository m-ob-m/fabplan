<?php 
    /**
     * \name		delete.php
     * \author    	Marc-olivier Bazin-Maurice
     * \version		1.0
     * \date       	2018-03-21
     *
     * \brief 		Suppression d'un modèle
     * \details 	Suppression d'un modèle
     */

    // Structure de retour vers javascript
    $responseArray = array("status" => null, "success" => array("data" => null), "failure" => array("message" => null));

    try
    {
        // INCLUDE
        require_once $_SERVER["DOCUMENT_ROOT"] . "/Planificateur/parametres/model/controller/modelController.php";
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
        
        // Structure de retour vers javascript
        $responseArray = array("status" => null, "success" => array("data" => null), "failure" => array("message" => null));
        
        $input =  json_decode(file_get_contents("php://input"));
        
        // Vérification des paramètres
        $id = (isset($input->id) ? $input->id : null);
        
        try
        {
            $db->getConnection()->beginTransaction();
            $model = \Model::withID($db, $id, \MYSQLDatabaseLockingReadTypes::FOR_UPDATE);
            if($model === null)
            {
                throw new \Exception("Il n'y a aucun modèle possédant l'identifiant unique \"{$id}\".");
            }
            
            $model->delete($db);
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
        $responseArray["success"]["message"] = null;
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