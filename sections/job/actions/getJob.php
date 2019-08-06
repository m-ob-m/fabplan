<?php 
    /**
     * \name		getJobTypes.php
     * \author    	Marc-Olivier Bazin-Maurice
     * \version		1.0
     * \date       	2018-08-20
     *
     * \brief 		Get JobTypes for a specified job id
     * \details     Get JobTypes for a specified job id
     */
    
    // Structure de retour vers javascript
    $responseArray = array("status" => null, "success" => array("data" => null), "failure" => array("message" => null));
    
    try
    {        
        // INCLUDE
        require_once __DIR__ . '/../../../lib/config.php';	// Fichier de configuration
        require_once __DIR__ . '/../../../lib/connect.php';	// Classe de connection à la base de données
        require_once __DIR__ . '/../controller/jobController.php'; // Classe contrôleur de la classe Job
        require_once __DIR__ . '/../../../parametres/model/controller/modelController.php'; // Classe contrôleur de la classe Model
        require_once __DIR__ . '/../../../parametres/type/controller/typeController.php'; // Classe contrôleur de la classe Type
        require_once __DIR__ . '/../../../parametres/generic/controller/genericController.php'; // Classe contrôleur de la classe Generic

        // Initialize the session
        session_start();
                                                                                            
        // Check if the user is logged in, if not then redirect him to login page
        if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
            if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')
            {
                throw new \Exception("You are not logged in.");
            }
            else
            {
                header("location: /Planificateur/lib/account/logIn.php");
            }
            exit;
        }

        // Closing the session to let other scripts use it.
        session_write_close();

        // Vérification des paramètres
        $jobId = $_GET["jobId"] ?? null;
        
        if(intval($jobId) != floatval($jobId) || !is_numeric($jobId) || intval($jobId) <= 0)
        {
            throw new \Exception("L'identifiant unique de Job fourni n'est pas un entier numérique positif.");
        }
        
        $job = null;
        $db = new \FabPlanConnection();
        try
        {
            $db->getConnection()->beginTransaction();
            $job = \Job::withID($db, $jobId);
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
        $responseArray["success"]["data"] = $job;
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