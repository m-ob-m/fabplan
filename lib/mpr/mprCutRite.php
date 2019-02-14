<?php
include_once __DIR__ . "/../../parametres/varmodtypegen/controller/modelTypeGenericController.php";
include_once __DIR__ . "/../../sections/batch/model/Carrousel.php";
include_once __DIR__ . "/mprExpressionEvaluator.php";

/**
 * \name		mprCutrite
* \author    	Mathieu Grenier
* \version		1.0
* \date       	2017-01-26
*
* \brief 		Génère un fichier MPR pour l'importation dans CutRite
* \details 		Génère un fichier MPR pour l'importation dans CutRite
*/

class mprCutrite 
{
	private $_generic;	// Contenu du fichier generique MPR pour éviter de le relire à chaque fois
	private $_mpr;		// Contenu en string du fichier MPR
	private $_blocks;	// Block de données
	private $_header;	// Entête du fichier MPR avant les blocs

	function __construct($genericFilePath)
	{
		$this->_header = "";
		
		$myfile = fopen($genericFilePath, "r") or die("Unable to open generic MPR file!");
		$this->_generic = fread($myfile,filesize($genericFilePath));
		fclose($myfile);
		
	}
	
	
	/**
	 * \name		extractMprBlocks
	 * \author    	Mathieu Grenier
	 * \version		1.0
	 * \date       	2017-01-27
	 *
	 * \brief       Extrait l'entête et les blocs du fichier MPR
	 * \details    	Extrait l'entête et les blocs du fichier MPR.
	 * 				Le tout se fait en mémoire pour un traitement + rapide
	 */
	public function extractMprBlocks()
	{	
		$lines = explode("\r\n", $this->_generic);
		$count = 2;
		
		$blockTxt = "";	// Tampon de lecture de bloc
		$header = false;
		$dp = 0;		// Nombre d'enfants d'un block
		$parent;		// Block parent
		
		foreach($lines as $line)
		{	
			if(substr($line, 0,1) == "<")
			{	
			    // Début d'un block
				if(!$header)
				{	
				    // Il s'agit de l'entête
					$this->_header = $blockTxt;
					$header = true;
				} 
				else 
				{	
				    // Il s'agit d'un bloc
					$this->addBlock($parent,$dp,$blockTxt);
				}
				
				$blockTxt = "";	// Réinitialiser le tampon de block
			}
			
			$blockTxt .= $line . "\r\n";	// Mise en tampon de la ligne
		}
		
		$this->addBlock($parent,$dp,$blockTxt);	// Dernier bloc
	}
	
	
	/**
	 * \name		addBlock
	 * \author    	Mathieu Grenier
	 * \version		1.0
	 * \date       	2017-01-27
	 *
	 * \brief       Ajout d'un block au MPR ou au parent
	 * \details    	Ajout d'un block au MPR ou au parent
	 */
	function addBlock(&$parent, &$dp, &$blockTxt)
	{	
		if($dp==0)
		{	
		    // Création d'un bloc parent
			$parent = new MprBlock($blockTxt);
			$dp = $parent->getDP();
		
			$this->_blocks[count($this->_blocks)] = $parent;
		} 
		else 
		{	
		    // Ajout d'un block enfant	
			$parent->addBlock(new MprBlock($blockTxt));
			$dp--;
		}
	}
	

	/**
	 * \name		makeMprFromJobType
	 * \author    	Mathieu Grenier
	 * \version		1.0
	 * \date       	2017-01-26
	 *
	 * \brief       Permet de générer le texte d'un fichier CSV à partir d'une job
	 * \details    	Permet de générer le texte d'un fichier CSV à partir d'une job
	 * 
	 * NB	html_entity_decode est utilisé présentement car l'importation semble créer
	 * 		parfois des caractères HTML
	 */
	public function makeMprFromJobType(JobType $jobType)
	{
	    $var_token = "";
	    $parameters = [];
	    
	    if($jobType->getModelId() === 2)
	    {
	        // Unification des sauts de ligne
	        $this->_generic = preg_replace("/(?<!\r)\n|\r(?!\n)/", "\r\n", $jobType->getMprFile());
	        
	        $this->extractMprBlocks();
	        $this->_mpr = $this->_header;
	        
	        $matches1 = array();
	        preg_match("/\[001\r\n(.*?)\r\n\r\n/s", $this->_header, $matches1);
	        $matches2 = array();
	        preg_match_all("/^(.*=\".*\")\r$/m", $matches1[1] ?? null, $matches2);
	        
	        if(isset($matches2[1]))
	        {
    	        foreach($matches2[1] as $parameterString)
    	        {
    	            if(!preg_match("/^KM=\".*\"$/", $parameterString))
    	            {
    	                $matches3 = array();
    	                preg_match("/^(.*)=\"(.*)\"$/", $parameterString, $matches3);
    	                $parameters[$matches3[1]] = $matches3[2];
    	            }
    	        }
	        }
	    }
	    else
	    { 
	        $modelId = $jobType->getModelId();
	        $typeNo = $jobType->getTypeNo();
	        $modelTypeGeneric = (new ModelTypeGenericController())->getModelTypeGeneric($modelId, $typeNo);
	        $modelTypeGenericParameters = $modelTypeGeneric->getParametersAsKeyDescriptionPairs();
	        foreach ($jobType->getParameters() as $parameter)
	        {
	            $key = $parameter->getKey();
	            $value = $parameter->getValue();
	            $parameters[$key] = $value;
	            $description = $modelTypeGenericParameters[$key];
	            
	            $var_token .= "{$key}=\"{$value}\"\r\n";
                $var_token .= "KM=\"{$description}\"\r\n";
	        }
	        
	        $this->extractMprBlocks();
	        $this->_mpr = str_replace("**var_token**\r\n", $var_token, $this->_header, $count);
	    }
	    $parameters = array_merge($parameters, (new Carrousel())->getSymbolicToolNamesArray());
	    
		$count = 1;
				
		$child_count = 0;	// Nombre d'enfants d'un parent;
		$child_text = "";	// Texte des enfants;
		
		foreach($this->getBlocks() as $bloc)
		{	
		    $condition = boolval(\MprExpression\Evaluator::evaluate($bloc->getCondition(), null, $parameters));
		    if($condition)
		    {
				$child_text = "";
				$child_count = 0;
				
				foreach($bloc->getChilds() as $child)
				{
				    $condition = boolval(\MprExpression\Evaluator::evaluate($child->getCondition(), null, $parameters));
				    if($condition)
					{
						$child_text .= $child->getText();
						$child_count++;	// Pour corriger DP au bon nombre
					}
				}
				
				// Modification du DP du parent et insertion de l'enfant
				$this->_mpr .= str_replace("DP=\"{$bloc->getDP()}\"", "DP=\"{$child_count}\"", $bloc->getText()) . $child_text;
			}
		}
		
		//Si le fichier mpr n'a pas de marque de fin (un !), on en ajoute une.
        $this->_mpr .= ((preg_match("/^.*!\s*$/s", $this->_mpr)) ? "" : "!");
		
        if($jobType->getModelId() === 2)
        {
    		//Remettre l'encodage des caractères en ISO-8859-1
    		$this->_mpr = utf8_decode($this->_mpr);
        }
	}
	
	
	/**
	 * \name		makeMprFromTest
	 * \author    	Marc-Olivier Bazin-Maurice
	 * \version		1.0
	 * \date       	2017-11-06
	 *
	 * \brief       Permet de générer le texte d'un fichier CSV à partir d'un Test
	 * \details    	Permet de générer le texte d'un fichier CSV à partir d'un Test
	 *
	 */
	public function makeMprFromTest(Test $test, array $paramDescription)
	{
	    $var_token = "";
	    $parameters = [];
	    
        if($test->getModelId() === 2)
        {
            // Unification des sauts de ligne
            $this->_generic = preg_replace("/(?<!\r)\n|\r(?!\n)/", "\r\n",$test->getFichierMpr());
            
            $this->extractMprBlocks();
            $this->_mpr = $this->_header;
            
            $matches1 = array();
            preg_match("/\[001\r\n(.*?)\r\n\r\n/s", $this->_header, $matches1);
            $matches2 = array();
            preg_match_all("/^(.*=\".*\")\r$/m", $matches1[1] ?? null, $matches2);
            
            if(isset($matches2[1]))
            {
                foreach($matches2[1] as $parameterString)
                {
                    if(!preg_match("/^KM=\".*\"$/", $parameterString))
                    {
                        $matches3 = array();
                        preg_match("/^(.*)=\"(.*)\"$/", $parameterString, $matches3);
                        $parameters[$matches3[1]] = $matches3[2];
                    }
                }
            }
        }
	    else
	    {
	        foreach ($test->getParameters() as $parameter)
    	    {
    	        $parameters[$parameter->getKey()] = $parameter->getValue();
    	        
    	        $var_token .= "{$parameter->getKey()}=\"{$parameter->getValue()}\"\r\n";
    	        $var_token .= "KM=\"{$parameter->getDescription()}\"\r\n";
    	    }
    	    
    	    $this->extractMprBlocks();
    	    $this->_mpr = str_replace("**var_token**\r\n", $var_token, $this->_header, $count);
	    }
	    $parameters = array_merge($parameters, (new Carrousel())->getSymbolicToolNamesArray());
	    $count = 1;
	    
	    $child_count = 0;	// Nombre d'enfants d'un parent;
	    $child_text = "";	// Texte des enfants;
	    
	    foreach($this->getBlocks() as $bloc)
	    {
	        $condition = boolval(\MprExpression\Evaluator::evaluate($bloc->getCondition(), null, $parameters));
	        if($condition)
	        {
	            $child_text = "";
	            $child_count = 0;
	            
	            //Identify the external profile section in order to skip condition evaluation.
	            $filter = '/(*ANYCRLF)^MNM="(?=.*profil.*extérieur.*)"$/im';
	            $isExternalProfile = preg_match($filter, $bloc->getText(), $array);
	            
	            foreach($bloc->getChilds() as $child)
	            {
	                $condition = boolval(\MprExpression\Evaluator::evaluate($child->getCondition(), null, $parameters));
	                if($condition || $isExternalProfile)
	                {
	                    $child_text .= $child->getText();
	                    $child_count++;	// Pour corriger DP au bon nombre
	                }
	            }
	            
	            // Modification du DP du parent et insertion de l'enfant
	            $this->_mpr .= str_replace("DP=\"{$bloc->getDP()}\"", "DP=\"{$child_count}\"", $bloc->getText()) . $child_text;
	            
	        }
	    }
	    
	    //Si le fichier mpr n'a pas de marque de fin (un !), on en ajoute une.
	    $this->_mpr .= ((preg_match("/^.*!\s*$/s", $this->_mpr)) ? "" : "!");
	    
	    //Remettre l'encodage des caractères en ISO-8859-1 si pertinent (les programmes stockés dans la BD sont encodés en UTF-8).
	    $this->_mpr = utf8_decode($this->_mpr);
	}
	

	/**
	 * \name		makeMprFile
	 * \author    	Mathieu Grenier
	 * \version		1.0
	 * \date       	2017-01-26
	 *
	 * \brief       Crée un fichier MPR selon le chemin d'accès indiqé
	 * \details    	Crée un fichier MPR selon le chemin d'accès indiqé
	 */
	public function makeMprFile($name){
		if(!$myfile = fopen($name, "w"))
		{
		    throw new \Exception("Unable to open file \"{$name}\"!");
		}
		fwrite($myfile, $this->_mpr);
		fclose($myfile);
	}
	
	
	public function getMprString(){
		return $this->_mpr;
	}
	
	public function getHeader(){
		return $this->_header;
	}
	
	public function getBlocks(){
		return $this->_blocks;
	}
	

}


/**
 * \name		MprBlock
 * \author    	Mathieu Grenier
 * \version		1.0
 * \date       	2017-01-27
 *
 * \brief 		Représente un block de condition dans un fichier MPR
 * \details 	Représente un block de condition dans un fichier MPR
 */
class MprBlock {
	
	private $_txt;
	private $_dp;
	private $_condition;
	private $_childs;
	private $_type;	// Type de block (Kommentar, etc...)
	
	function __construct($txt){
		
		$this->_txt = $txt;
		$this->_childs = array();
		$this->_dp = 0;
		
		$this->readTxt();
		
	}
	
	/**
	 * \name		readTxt
	 * \author    	Mathieu Grenier
	 * \version		1.0
	 * \date       	2017-01-27
	 *
	 * \brief       Lit le block et extrait les variables nécessaires
	 * \details    	Lit le block et extrait les variables nécessaires
	 */
	function readTxt()
	{		
		$lines = explode("\r\n", $this->_txt);
		$count = 2;
		
		foreach($lines as $line)
		{	
			if(substr($line, 0, 1) == "<")
			{
				$this->_type = $line;
			}
			
			switch(substr($line,0,3))
			{
				case "DP=":	// Indique le nombre d'enfants du block
					$this->_dp = str_replace("\"", "", substr($line,3,strlen($line)) , $count);
					break;
					
				case "??=":	// Indique la condition pour que le block s'applique
					$this->_condition = str_replace("\"", "", substr($line,3,strlen($line)) , $count);
					break;
			}
		}
	}
	
	/**
	 * \name		addBlock
	 * \author    	Mathieu Grenier
	 * \version		1.0
	 * \date       	2017-01-27
	 *
	 * \brief       Ajout d'un bloc enfant
	 * \details    	Ajout d'un bloc enfant
	 */
	function addBlock($bloc)
	{
		$this->_childs[count($this->_childs)] = $bloc; 
	}
	
	
	public function getDP()
	{
		return $this->_dp;
	}
	
	public function getChilds()
	{
		return $this->_childs;
	}
	
	public function getText()
	{
		return $this->_txt;		
	}
	
	public function getCondition()
	{
		return $this->_condition;
	}
}
?>