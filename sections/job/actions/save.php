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

// INCLUDE
include_once __DIR__ . '/../../../lib/config.php';	// Fichier de configuration
include_once __DIR__ . '/../../../lib/connect.php';	// Classe de connection à la base de données
include_once __DIR__ . '/../controller/jobController.php'; // Contrôleur de Job
include_once __DIR__ . '/../../../parametres/generic/controller/genericController.php'; // Contrôleur de Generic
include_once __DIR__ . '/../../../parametres/model/controller/modelController.php'; // Contrôleur de Model
include_once __DIR__ . '/../../../parametres/type/controller/typeController.php'; // Contrôleur de Type

//Structure de retour vers javascript
$responseArray = array("status" => null, "success" => array("data" => null), "failure" => array("message" => null));

try
{
    $inputJob =  json_decode(file_get_contents("php://input"));
    
    $db = new \FabPlanConnection();
    try
    {
        $db->getConnection()->beginTransaction();
        buildJob($db, $inputJob)->save($db);
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
                throw \Exception("Il n'y a pas de modèle avec l'identifiant unique \"{$inputJobType->model->id}\".");
            }
            
            $type = \Type::withImportNo($db, $inputJobType->type->importNo);
            if($type === null)
            {
                throw \Exception("Il n'y a pas de type avec l'identifiant unique \"{$inputJobType->type->importNo}\".");
            }
            
            $generic = \Generic::withID($db, $type->getGenericId());
            
            $parts = array();
            if(!empty($inputJobType->parts))
            {
                foreach($inputJobType->parts as $inputPart)
                {
                    $part = new \JobTypePorte($inputPart->id, $inputJobType->id, $inputPart->quantityToProduce,
                        $inputPart->producedQuantity ?? 0, $inputPart->length, $inputPart->width, $inputPart->grain,
                        $inputPart->done ?? "N", null);
                    array_push($parts, $part);
                }
            }
            
            $parameters = array();
            $mprFile = null;
            if($model->getId() !== 2)
            {
                foreach($generic->getGenericParameters() as $genericParameter)
                {
                    $key = $genericParameter->getKey();
                    $genericValue = $genericParameter->getValue();
                    $value = $inputJobType->jobTypeParameters->{$key};
                    if($value !== $genericValue && $value !== null && $value !== "")
                    {
                        $parameter = new \JobTypeParameter($inputJobType->id, $key, $value);
                        array_push($parameters, $parameter);
                    }
                }
            }
            else
            {
                $mprfile = $inputJobType->mprFile;
            }
            
            $jobType = new \JobType($inputJobType->id, $inputJob->id, $model->getId(), $type->getImportNo(),
                $mprFile, null, null, $parameters, $parts);
            array_push($jobTypes, $jobType);
        }
    }
    
    $status = null;
    $job = \Job::withID($inputJob->id);
    if($job === null)
    {
        throw new \Exception("La création de job n'a pas encore été implémentée.");
    }
    return $job->setDeliveryDate($inputJob->deliveryDate)->setJobTypes($jobTypes);
}