<?php
/**
* @file plugins/importexport/crossref/CrossrefExportDeployment.inc.php*
 **/

define('CROSSREF_XSI_SCHEMAVERSION', '4.4.2');
define('CROSSREF_XMLNS', 'http://www.crossref.org/schema/4.4.2');
define('XMLNS_XSI', 'http://www.w3.org/2001/XMLSchema-instance');
define('CROSSREF_XSI_SCHEMA_LOCATION', 'https://www.crossref.org/schemas/crossref4.4.2.xsd');
define('CROSSREF_XMLNS_JATS' , 'http://www.ncbi.nlm.nih.gov/JATS1');
define('CROSSREF_XMLNS_AI' , 'http://www.crossref.org/AccessIndicators.xsd');
define('CROSSREF_XMLNS_REL' , 'http://www.crossref.org/relations.xsd');
define('CROSSREF_XML_ROOT_ELEMENT' , 'doi_batch');

use Illuminate\Database\Capsule\Manager as Capsule;
import('lib.pkp.classes.plugins.importexport.PKPImportExportDeployment');

class CrossrefExportDeployment extends PKPImportExportDeployment {

	var $_context;

	var $_plugin;

	function __construct($request, $plugin) {
		$context = $request->getContext();
		parent::__construct($context, $plugin);
		$this->_context = $context;
		$this->_plugin = $plugin;
	}

	function getNamespace() {
		return CROSSREF_XMLNS;
	}

	function getPlugin() {
		return $this->_plugin;
	}

	function setPlugin($plugin) {
		$this->_plugin = $plugin;
	}

	function getRootElementName() {
		return CROSSREF_XML_ROOT_ELEMENT;
	}

	function getXmlSchemaVersion() {
		return CROSSREF_XSI_SCHEMAVERSION;
	}

	function getXmlSchemaInstance() {
		return XMLNS_XSI;
	}

	function getSchemaLocation() {
		return CROSSREF_XSI_SCHEMA_LOCATION;
	}

	function getJATSNamespace() {
		return CROSSREF_XMLNS_JATS;
	}

	function getAINamespace() {
		return CROSSREF_XMLNS_AI;
	}

	function getRELNamespace() {
		return CROSSREF_XMLNS_REL;
	}

	function createNodes($documentNode, $submission, $publication) {
		$documentNode = $this->createRootNode($documentNode);
		$documentNode = $this->createHeadNode($documentNode);
		$bodyNode = $documentNode->createElement('body');
		$bodyNode->appendChild($this->createBookNode($documentNode, $submission, $publication));
		$documentNode->documentElement->appendChild($bodyNode);
		return $documentNode;
	}

	function createRootNode($documentNode) {
		$rootNode = $documentNode->createElementNS($this->getNamespace(), $this->getRootElementName());
		$rootNode->setAttribute('version', $this->getXmlSchemaVersion());
		$rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', $this->getXmlSchemaInstance());
		$rootNode->setAttribute('xsi:schemaLocation', $this->getNamespace() . ' ' . $this->getSchemaLocation());
		$rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:jats', $this->getJATSNamespace());
		$rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ai', $this->getAINamespace());
		$rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:rel', $this->getRELNamespace());
		$documentNode->appendChild($rootNode);
		return $documentNode;
	}

	function createHeadNode($documentNode) {
		$request = Application::get()->getRequest();
		$press = $request->getPress();
		$headNode = $documentNode->createElementNS($this->getNamespace(), 'head');
		$timestamp = date("YmdHis")."000";
		$headNode->appendChild($node = $documentNode->createElementNS($this->getNamespace(), 'doi_batch_id', $this->xmlEscape($press->getData('initials', $press->getPrimaryLocale()) . '_' .  $timestamp)));
		$headNode->appendChild($node = $documentNode->createElementNS($this->getNamespace(), 'timestamp', $timestamp));
		$depositorNode = $documentNode->createElementNS($this->getNamespace(), 'depositor');
		$depositorName = $press->getData('supportName');
		$depositorEmail = $press->getData('supportEmail');
		$depositorNode->appendChild($node = $documentNode->createElementNS($this->getNamespace(), 'depositor_name', $this->xmlEscape($depositorName)));
		$depositorNode->appendChild($node = $documentNode->createElementNS($this->getNamespace(), 'email_address', $this->xmlEscape($depositorEmail)));
		$headNode->appendChild($depositorNode);
		$publisherServer = $press->getLocalizedName();
		$headNode->appendChild($node = $documentNode->createElementNS($this->getNamespace(), 'registrant', $this->xmlEscape($publisherServer)));
		$documentNode->documentElement->appendChild($headNode);
		return $documentNode;
	}

	function createBookNode($documentNode, $submission, $publication) {
		if ($submission->getWorkType() == WORK_TYPE_EDITED_VOLUME) {
			$type = 'edited_book';
		} else {
			$type = 'monograph';
		}
		$bookNode = $documentNode->createElement("book");
		$bookNode->setAttribute('book_type', $type);
		$bookNode->appendChild($this->createBookMetadataNode($documentNode, $submission, $publication));
		$chapters = $publication->getData('chapters');
		foreach ($chapters as $chapter) {
			$bookNode->appendChild($this->createContentItemNode($documentNode, $submission, $publication, $chapter));
		}
		return $bookNode;
	}
	function createBookMetadataNode($documentNode, $submission, $publication) {
		$request = Application::get()->getRequest();
		$press = $request->getPress();
		$locale = $publication->getData('locale');
	
		// Verificar se $locale está vazio e atribuir 'pt' como valor padrão
		if (empty($locale)) {
			$locale = 'pt';
		}

		// If the book is part of series use book_series_metadata else use book_metadata
		// Consider adding book_set_metadata option in cases where a series does not have an ISSN
		$seriesDao = DAORegistry::getDAO('SeriesDAO'); /* @var $seriesDao SeriesDAO */
		$series = $seriesDao->getById($publication->getData('seriesId'));
		if ($series && ($series->getOnlineISSN() || $series->getPrintISSN())){
			$bookMetadataNodeType = 'book_series_metadata';
		} else{
			$bookMetadataNodeType = 'book_metadata';
		}

		$bookMetadataNode = $documentNode->createElementNS($this->getNamespace(), $bookMetadataNodeType);
		$bookMetadataNode->setAttribute('language', PKPLocale::getIso1FromLocale($locale));

		// If a series, add series metadata
		if ($series && ($series->getOnlineISSN() || $series->getPrintISSN())){
			$bookMetadataNode->appendChild($this->createSeriesMetadataNode($documentNode, $series));
		}

		// Contributors: editors and authors
		if ($authors = $publication->getData('authors')) {
			$bookMetadataNode->appendChild($this->createContributorsNode($documentNode, $authors, $locale));
		}

		// Book title
		$titlesNode = $documentNode->createElement("titles");
		$titlesNode->appendChild($node = $documentNode->createElement("title", $this->xmlEscape($publication->getLocalizedData('title', $locale))));
		if ($subtitle = $publication->getLocalizedData('subtitle', $locale)){
			$titlesNode->appendChild($node = $documentNode->createElement('subtitle', $this->xmlEscape($subtitle)));
		}
		$bookMetadataNode->appendChild($titlesNode);

		// Abstract
		if ($abstract = $publication->getLocalizedData('abstract', $locale)) {
			$abstractNode = $documentNode->createElementNS($this->getJATSNamespace(), 'jats:abstract');
			$abstractNode->appendChild($node = $documentNode->createElementNS($this->getJATSNamespace(), 'jats:p', htmlspecialchars(html_entity_decode(strip_tags($abstract), ENT_COMPAT, 'UTF-8'), ENT_COMPAT, 'UTF-8')));
			$bookMetadataNode->appendChild($abstractNode);
		}

		// Book publication date
		$datePublished = $submission->getDatePublished();
		$publicationDateNode = $documentNode->createElement("publication_date");
		$publicationDateNode->setAttribute('media_type', 'online');
		$publicationDateNode->appendChild($node = $documentNode->createElement('month', $this->xmlEscape( date('m', strtotime($datePublished)) )));
		$publicationDateNode->appendChild($node = $documentNode->createElement('day', $this->xmlEscape( date('d', strtotime($datePublished)) )));
		$publicationDateNode->appendChild($node = $documentNode->createElement('year', $this->xmlEscape( date('Y', strtotime($datePublished)) )));
		$bookMetadataNode->appendChild($publicationDateNode);

		// ISBN
		$publicationFormats = $publication->getData('publicationFormats');
		$noIsbn = true;
		foreach ($publicationFormats as $publicationFormat) {
			$identificationCodes = $publicationFormat->getIdentificationCodes();
			while ($identificationCode = $identificationCodes->next()) {
				if ($identificationCode->getCode() == "02" || $identificationCode->getCode() == "15") {
					// 02 and 15: ONIX codes for ISBN-10 or ISBN-13
					$isbnNode = $documentNode->createElement('isbn', $this->xmlEscape($identificationCode->getValue()));
					$isbnNode->setAttribute('media_type', $publicationFormat->getPhysicalFormat() ? 'print' : 'electronic');
					$bookMetadataNode->appendChild($isbnNode);
					$noIsbn = false;
				}
			}
		}
		if ($noIsbn){
			$noIsbnNode = $documentNode->createElement('noisbn');
			$noIsbnNode->setAttribute('reason', 'archive_volume'); // Consider OMP book setting for noisbn?
			$bookMetadataNode->appendChild($noIsbnNode);
		}

		// Book publisher
		$publisherNode = $documentNode->createElementNS($this->getNamespace(), 'publisher');
		$publisherNode->appendChild($node = $documentNode->createElementNS($this->getNamespace(), 'publisher_name', $this->xmlEscape($press->getData('publisher'))));
		$bookMetadataNode->appendChild($publisherNode);

		// DOI data
		$doi = $publication->getData('pub-id::doi');
		$doiDataNode = $documentNode->createElement("doi_data");
		$doiDataNode->appendChild($node = $documentNode->createElement('doi', $this->xmlEscape($doi)));
		$doiDataNode->appendChild($node = $documentNode->createElement('resource', $this->xmlEscape($request->url($press->getPath(), 'catalog', 'book', $submission->getBestId(), null, null, true))));
		$bookMetadataNode->appendChild($doiDataNode);

		return $bookMetadataNode;
	}

	function createSeriesMetadataNode($documentNode, $series) {
			$request = Application::get()->getRequest();
			$press = $request->getPress();

			$seriesMetadataNode = $documentNode->createElement('series_metadata');

			// Series title
			$titlesNode = $documentNode->createElement("titles");
			$titlesNode->appendChild($documentNode->createElement("title", $this->xmlEscape($series->getLocalizedData('title', $press->getPrimaryLocale())))); // Always use press primary locale, because only one series title can be attached to an ISSN in Crossref registry
			$seriesMetadataNode->appendChild($titlesNode);

			// Series ISSN
			if ($series->getOnlineISSN()) {
				$issnNode = $documentNode->createElement("issn", $this->xmlEscape($series->getOnlineISSN()));
				$issnNode->setAttribute('media_type', 'electronic');
			} elseif ($series->getPrintISSN()){
				$issnNode = $documentNode->createElement("issn", $this->xmlEscape($series->getPrintISSN()));
				$issnNode->setAttribute('media_type', 'print');
			} else{
				throw new \Exception("Series has no ISSN!");
			}
			$seriesMetadataNode->appendChild($issnNode);

		return $seriesMetadataNode;
	}

	function createContentItemNode($documentNode, $submission, $publication, $chapter) {
		$request = Application::get()->getRequest();
		$press = $request->getPress();
		$locale = $publication->getData('locale');

		$contentItemNode = $documentNode->createElement('content_item');
		$contentItemNode->setAttribute('language', PKPLocale::getIso1FromLocale($locale));
		$contentItemNode->setAttribute('component_type', 'chapter');

		$chapterFiles = Capsule::table('submission_file_settings')
			->where('setting_name', '=', 'chapterId')
			->where('setting_value', '=', $chapter->getId())
			->get();

		if ($chapterFiles) {
			$contentItemNode->setAttribute('publication_type', 'full_text');
		} elseif ($chapter->getLocalizedData('abstract', $locale)) {
			$contentItemNode->setAttribute('publication_type', 'abstract_only');
		} else {
			$contentItemNode->setAttribute('publication_type', 'bibliographic_record');
		}

		// Chapter authors
		$chapterAuthorDao = DAORegistry::getDAO('ChapterAuthorDAO'); /** @var $chapterAuthorDao ChapterAuthorDAO */
		if ($authors = $chapterAuthorDao->getAuthors($chapter->getData('publicationId'), $chapter->getId())->toArray()) {
			$contentItemNode->appendChild($this->createContributorsNode($documentNode, $authors, $locale, true));
		}

		// Abstract
		if ($abstract = $chapter->getLocalizedData('abstract', $locale)) {
			$abstractNode = $documentNode->createElementNS($this->getJATSNamespace(), 'jats:abstract');
			$abstractNode->appendChild($node = $documentNode->createElementNS($this->getJATSNamespace(), 'jats:p', htmlspecialchars(html_entity_decode(strip_tags($abstract), ENT_COMPAT, 'UTF-8'), ENT_COMPAT, 'UTF-8')));
			$contentItemNode->appendChild($abstractNode);
		}

		// Chapter title
		$titlesNode = $documentNode->createElement("titles");
		$titlesNode->appendChild($node = $documentNode->createElement("title", $this->xmlEscape($chapter->getLocalizedData('title', $locale))));
		if ($subtitle = $chapter->getLocalizedData('subtitle', $locale)){
			$titlesNode->appendChild($node = $documentNode->createElement('subtitle', $this->xmlEscape($subtitle)));
		}
		$contentItemNode->appendChild($titlesNode);

		// Date published
		$datePublished = $submission->getDatePublished();
		$publicationDateNode = $documentNode->createElement("publication_date");
		$publicationDateNode->setAttribute('media_type', 'online');
		$publicationDateNode->appendChild($node = $documentNode->createElement('month', $this->xmlEscape( date('m', strtotime($datePublished)) )));
		$publicationDateNode->appendChild($node = $documentNode->createElement('day', $this->xmlEscape( date('d', strtotime($datePublished)) )));
		$publicationDateNode->appendChild($node = $documentNode->createElement('year', $this->xmlEscape( date('Y', strtotime($datePublished)) )));
		$contentItemNode->appendChild($publicationDateNode);

		// DOI data
		$doi = $chapter->getData('pub-id::doi');
		$doiDataNode = $documentNode->createElement("doi_data");
		$doiDataNode->appendChild($node = $documentNode->createElement('doi', $this->xmlEscape($doi)));
		$doiDataNode->appendChild($node = $documentNode->createElement('resource', $this->xmlEscape($request->url($press->getPath(), 'catalog', 'book', $submission->getBestId(), null, null, true)))); // Use submission landing page while chapter landing page not available
		$contentItemNode->appendChild($doiDataNode);

		return $contentItemNode;
	}

/**
 * Cria um elemento XML para os contribuidores (autores ou editores).
 *
 * @param DOMDocument $documentNode O nó do documento XML.
 * @param array $authors Um array de objetos representando os autores ou editores.
 * @param string $locale O idioma usado para os elementos XML.
 * @param bool $isChapter Define se os contribuidores são para um capítulo.
 * @return DOMElement O elemento XML que contém informações sobre os contribuidores.
 */
function createContributorsNode($documentNode, $authors, $locale, $isChapter = false) {
    // Cria um elemento 'contributors' como nó pai.
    $contributorsNode = $documentNode->createElement('contributors');
    // Variável para rastrear se é o primeiro contribuidor.
    $isFirst = true;

    // Loop através dos autores/editores.
    foreach ($authors as $author) {
        // Cria um elemento 'person_name' para cada autor/editor.
        $personNameNode = $documentNode->createElement('person_name');
        // Variável para rastrear se o autor/editor tem um nome alternativo.
        $hasAltName = false;

        // Determina o papel do contribuidor (autor ou editor).
        if ($isChapter || !$author->getData('isVolumeEditor')) {
            $personNameNode->setAttribute('contributor_role', 'author');
        } else {
            $personNameNode->setAttribute('contributor_role', 'editor');
        }

        // Atribui a sequência do contribuidor (primeiro ou adicional).
        if ($isFirst) {
            $personNameNode->setAttribute('sequence', 'first');
        } else {
            $personNameNode->setAttribute('sequence', 'additional');
        }

        // Obtém os nomes de família e dados dos autores.
        $familyNames = $author->getFamilyName(null);
        $givenNames = $author->getGivenName(null);

        // Verifica se há nomes de família e dados de autores.
        if (!empty($familyNames) && !empty($givenNames)) {
            // Atribui o idioma ao nó 'person_name'.
            $personNameNode->setAttribute('language', PKPLocale::getIso1FromLocale($locale));

            // Loop pelos nomes dados em diferentes idiomas.
            foreach ($givenNames as $locale => $givenName) {
                if (!empty($givenName)) {
                    // Adiciona o nome dado ao nó 'person_name'.
                    $personNameNode->appendChild($documentNode->createElement('given_name', htmlspecialchars(ucfirst($givenName), ENT_COMPAT, 'UTF-8')));
                }
            }

            // Loop pelos sobrenomes em diferentes idiomas.
            foreach ($familyNames as $locale => $familyName) {
                if (!empty($familyName)) {
                    // Adiciona o sobrenome ao nó 'person_name'.
                    $personNameNode->appendChild($documentNode->createElement('surname', htmlspecialchars(ucfirst($familyName), ENT_COMPAT, 'UTF-8')));
                }
            }

            // Variável para rastrear se há um nome alternativo.
            $hasAltName = false;

            // Loop pelos sobrenomes em outros idiomas.
            foreach ($familyNames as $otherLocal => $familyName) {
                if ($otherLocal != $locale && isset($familyName) && !empty($familyName)) {
                    // Verifica se é o primeiro nome alternativo.
                    if (!$hasAltName) {
                        // Cria o nó 'alt-name' se o nome alternativo existir.
                        $altNameNode = $documentNode->createElement('alt-name');
                        $hasAltName = true;
                    }

                    // Cria um nó 'name' para o nome alternativo.
                    $nameNode = $documentNode->createElement('name');
                    $nameNode->setAttribute('language', PKPLocale::getIso1FromLocale($otherLocal));

                    // Adiciona o sobrenome ao nó 'name'.
                    $nameNode->appendChild($documentNode->createElement('surname', htmlspecialchars(ucfirst($familyName), ENT_COMPAT, 'UTF-8')));

                    // Adiciona o nome dado se estiver disponível.
                    if (isset($givenNames[$otherLocal]) && !empty($givenNames[$otherLocal])) {
                        $nameNode->appendChild($documentNode->createElement('given_name', htmlspecialchars(ucfirst($givenNames[$otherLocal]), ENT_COMPAT, 'UTF-8')));
                    }

                    // Adiciona o nó 'name' ao nó 'alt-name'.
                    $altNameNode->appendChild($nameNode);
                }
            }
        }

        // Adiciona o ORCID se estiver disponível.
        if ($author->getData('orcid')) {
            $personNameNode->appendChild($documentNode->createElement('ORCID', $author->getData('orcid')));
        }

        // Adiciona o nó 'alt-name' ao nó 'person_name' se houver um nome alternativo.
        if ($hasAltName && isset($altNameNode)) {
            $personNameNode->appendChild($altNameNode);
        }

        // Adiciona o nó 'person_name' ao nó 'contributors'.
        $contributorsNode->appendChild($personNameNode);

        // Define $isFirst como false após o primeiro contribuidor.
        $isFirst = false;
    }

    // Retorna o nó 'contributors' completo.
    return $contributorsNode;
}


	function xmlEscape($value) {
		return XMLNode::xmlentities($value, ENT_NOQUOTES);
	}

	function getContext() {
		return $this->_context;
	}

	function setContext($context) {
		$this->_context = $context;
	}


}
