<?php
    require_once __DIR__ . "/../../parametres/generic/controller/genericController.php";
    
    /**
    * \name		   CsvCutrite
    * \author    	Mathieu Grenier
    * \version		1.0
    * \date       	2017-01-26
    *
    * \brief 		Génère un fichier CSV pour l'importation dans CutRite
    * \details 		Génère un fichier CSV pour l'importation dans CutRite
    */
    class CsvCutrite 
    {
    	private $_csv;	// Contenu du fichier CSV
    
    	/**
    	 * Creates a new Cut Rite csv file
    	 *
    	 * @throws
    	 * @author Marc-Olivier Bazin-Maurice
    	 * @return \CsvCutrite This CsvCutrite
    	 */
    	function __construct()
    	{	
    	}
        
    	/**
         * Converts a job into a csv string 
         *
         * @param \Job $job A Job object
         * @param \Materiel $material A Materiel object
         *
         * @throws
         * @author Marc-Olivier Bazin-Maurice
         * @return 
         */
    	public function makeCsvFromJob(\Job $job, \Materiel $material) : void
    	{	
    	    /* @var $jobType JobType */
    		foreach($job->getJobTypes() as $jobType)
    		{
    		    /* @var $part JobTypePorte */
                foreach($jobType->getParts() as $part)  
                {
					$generic = $jobType->getType()->getGeneric();
					$modelId = $jobType->getModel()->getId();
					$typeNo = $jobType->getType()->getImportNo();
					$height = ($generic->getHeightParameter() === "LPX") ? $part->getLength() : $part->getWidth();
					$width = ($generic->getHeightParameter() === "LPX") ? $part->getWidth() : $part->getLength();
					$grain = $part->getGrain();
					if($generic->getHeightParameter() === "LPY")
					{
						if($grain === "X")
						{
							$grain = "Y";
						}
						elseif($grain === "Y")
						{
							$grain = "X";
						}
					}
                    $this->_csv .=  "{$modelId}_{$typeNo}_{$jobType->getId()};" . 
                        "{$material->getCodeCutRite()};" . 
                        "{$part->getQuantityToProduce()};" . 
    					"{$height};" .
    					"{$width};" .
    					"{$typeNo};" .
    					"{$grain};" .
    					"{$job->getName()}_{$part->getId()}\n"; 
    			}
    			
    		}
    	}
    	
    	
    	/**
         * Converts a batch into a csv string 
         *
         * @param \Batch $batch A Batch object
         *
         * @throws
         * @author Marc-Olivier Bazin-Maurice
         * @return 
         */
    	public function makeCsvFromBatch(\Batch $batch) : void
    	{	
    	    /* @var $job Job */
    		foreach($batch->getJobs() as $job)
    		{
    		    $material = \Materiel::withID(new \FabPlanConnection(), $batch->getMaterialId());
    		    $this->makeCsvFromJob($job, $material);
    		}		
    	}
    	
    	
    	/**
         * Makes a Cut Rite csv file 
         *
         * @param string $path The path to the csv file.
         *
         * @throws
         * @author Marc-Olivier Bazin-Maurice
         * @return 
         */
    	public function makeCsvFile( string $path) : void
    	{
    		if(!$myfile = fopen($path, "w"))
    		{
    		    throw new \Exception("Unable to open file \"{$path}\"!");
    		}
    		fwrite($myfile, $this->_csv);
    		fclose($myfile);
    	}
    	
    	
    	/**
    	 * Makes a Cut Rite csv file
    	 *
    	 * @throws
    	 * @author Marc-Olivier Bazin-Maurice
    	 * @return null|string The contents of the csv file.
    	 */
    	public function getCsvString() : ?string
    	{
    		return $this->_csv;
    	}
    	
    
    }
?>