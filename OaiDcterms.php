<?php declare(strict_types=1);
/**
 * @author John Flatness, Yu-Hsun Lin
 * @copyright Copyright 2009 John Flatness, Yu-Hsun Lin
 * @copyright BibLibre, 2016
 * @copyright Daniel Berthereau, 2014-2018
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */
namespace OaiPmhRepository\OaiPmh\Metadata;

use DOMElement;
use Omeka\Api\Representation\ItemRepresentation;

/**
 * Class implementing metadata output for the oai_dcterms metadata format.
 * oai_dcterms is output of the 55 Dublin Core terms.
 *
 * This format is not standardized, but used by some repositories.
 * Note: the namespace and the schema don’t exist. It is designed as an extended
 * version of oai_dc.
 *
 * @link http://www.bl.uk/schemas/
 * @link http://dublincore.org/documents/dc-xml-guidelines/
 * @link http://dublincore.org/schemas/xmls/qdc/dcterms.xsd
 */
class OaiDcterms extends AbstractMetadata
{
    /** OAI-PMH metadata prefix */
    const METADATA_PREFIX = 'oai_dcterms';

    /** XML namespace for output format */
    const METADATA_NAMESPACE = 'http://www.openarchives.org/OAI/2.0/oai_dcterms/';

    /** XML schema for output format */
    const METADATA_SCHEMA = 'http://www.openarchives.org/OAI/2.0/oai_dcterms.xsd';

    /** XML namespace for Dublin Core */
    const DCTERMS_NAMESPACE_URI = 'http://purl.org/dc/terms/';

    /**
     * Appends Dublin Core terms metadata.
     *
     * {@inheritDoc}
     */
    public function appendMetadata(DOMElement $metadataElement, ItemRepresentation $item): void
    {
        $document = $metadataElement->ownerDocument;

        $oai = $document->createElementNS(self::METADATA_NAMESPACE, 'oai_dcterms:dcterms');
        $metadataElement->appendChild($oai);

        /* Must manually specify XML schema uri per spec, but DOM won't include
         * a redundant xmlns:xsi attribute, so we just set the attribute
         */
        $oai->setAttribute('xmlns:dcterms', self::DCTERMS_NAMESPACE_URI);
        $oai->setAttribute('xmlns:xsi', parent::XML_SCHEMA_NAMESPACE_URI);
        $oai->setAttribute('xsi:schemaLocation', self::METADATA_NAMESPACE . ' ' .
            self::METADATA_SCHEMA);

        // Each of the 55 Dublin Core terms, in the Omeka order.
        $localNames = [
            // Dublin Core Elements.
            'title',
            'description',
            'creator',
            'subject',
            'publisher',
            'contributor',
            'date',
            'type',
            'format',
            'identifier',
            'source',
            'language',
            'relation',
            'coverage',
            'rights',
            // Dublin Core terms.
            'audience',
            'alternative',
            'tableOfContents',
            'abstract',
            'created',
            'valid',
            'available',
            'issued',
            'modified',
            'extent',
            'medium',
            'isVersionOf',
            'hasVersion',
            'isReplacedBy',
            'replaces',
            'isRequiredBy',
            'requires',
            'isPartOf',
            'hasPart',
            'isReferencedBy',
            'references',
            'isFormatOf',
            'hasFormat',
            'conformsTo',
            'spatial',
            'temporal',
            'mediator',
            'dateAccepted',
            'dateCopyrighted',
            'dateSubmitted',
            'educationLevel',
            'accessRights',
            'bibliographicCitation',
            'license',
            'rightsHolder',
            'provenance',
            'instructionalMethod',
            'accrualMethod',
            'accrualPeriodicity',
            'accrualPolicy',
        ];

        /* Must create elements using createElement to make DOM allow a
         * top-level xmlns declaration instead of wasteful and non-
         * compliant per-node declarations.
         */
        $values = $this->filterValuesPre($item);
        foreach ($localNames as $localName) {
            $term = 'dcterms:' . $localName;
            $termValues = $values[$term]['values'] ?? [];
            $termValues = $this->filterValues($item, $term, $termValues);
            foreach ($termValues as $value) {
                list($text, $attributes) = $this->formatValue($value);
                $attributes["comment"]=$values["$term"]["alternate_comment"] ?? "";
                $this->appendNewElement($oai, $term, $text, $attributes);
            }
        }

	$valuesAll=$item->values();
        if (isset($valuesAll["bibo:uri"])) {
           $wikidata_uri=$valuesAll["bibo:uri"];
           foreach ($wikidata_uri["values"] as $k=>$v) {
                   list($text, $attributes) = $this->formatValue($v);
                   $attributes["type"]='wikidata';
                   $attributes["comment"]=$wikidata_uri["alternate_comment"];
	           $this->appendNewElement($oai, "dcterms:identifier", $text , $attributes);
                   break;
           }
        }

        // Luettelotunniste -> dcterms:identifier
	$valuesAll=$item->values();
        if (isset($valuesAll["schema:catalogNumber"])) {
           $wikidata_uri=$valuesAll["schema:catalogNumber"];
           foreach ($wikidata_uri["values"] as $k=>$v) {
                   list($text, $attributes) = $this->formatValue($v);
                   $attributes["type"]='wikidata:P217';
                   $attributes["comment"]=$wikidata_uri["alternate_comment"];
	           $this->appendNewElement($oai, "dcterms:identifier", $text , $attributes);
                   break;
           }
        }

        $appendIdentifier = $this->singleIdentifier($item);
        if ($appendIdentifier) {
            $this->appendNewElement($oai, 'dcterms:identifier', $appendIdentifier, ['xsi:type' => 'dcterms:URI']);
        }

        // Schema:collection -> dcterms:isPartOf
        if (isset($valuesAll["schema:collection"])) {
           $wikidata_uri=$valuesAll["schema:collection"];
           foreach ($wikidata_uri["values"] as $k=>$v) {
                   list($text, $attributes) = $this->formatValue($v); 
                   $attributes["comment"]='wikidata:P195 (collection)';
                   $attributes["comment"]=$wikidata_uri["alternate_comment"];
	           $this->appendNewElement($oai, "dcterms:isPartOf", $text, $attributes );
           }
        }

        // Also append an identifier for each file
        if ($this->params['expose_media']) {
            foreach ($item->media() as $media) {
                $images=$media->thumbnailUrls();
                foreach($images as $k=>$v) {
	                $this->appendNewElement($oai, 'file', $v, ['bundle'=>$k]);
                }
                $this->appendNewElement($oai, 'file', $media->originalUrl(), ['bundle'=>'original', 'sha256'=>$media->sha256(), 'mimetype'=>$media->mediaType()]);
            }
        }

        // Schema:material
        if (isset($valuesAll["schema:material"])) {
           $wikidata_uri=$valuesAll["schema:material"];
           foreach ($wikidata_uri["values"] as $k=>$v) {
                   list($text, $attributes) = $this->formatValue($v); 
                   $attributes["type"]='wikidata:P186';
                   $attributes["comment"]=$wikidata_uri["alternate_comment"];
	           $this->appendNewElement($oai, "material", $text, $attributes );
           }
        }

        // Schema:width,height,depth
        $width=array();
        if (isset($valuesAll["schema:width"])) {
           $wikidata_uri=$valuesAll["schema:width"];
           foreach ($wikidata_uri["values"] as $k=>$v) {
                   list($text, $attributes) = $this->formatValue($v); 
                   array_push($width, $text);
           }
        }

        $height=array();
        if (isset($valuesAll["schema:height"])) {
           $wikidata_uri=$valuesAll["schema:height"];
           foreach ($wikidata_uri["values"] as $k=>$v) {
                   list($text, $attributes) = $this->formatValue($v); 
                   array_push($height, $text);
           }
        }
        $depth=array();
        if (isset($valuesAll["schema:depth"])) {
           $wikidata_uri=$valuesAll["schema:depth"];
           foreach ($wikidata_uri["values"] as $k=>$v) {
                   list($text, $attributes) = $this->formatValue($v); 
                   array_push($depth, $text);
           }
        }
        if (count($width)==1 && count($height)==1 && count($depth)==1) {
                   $text=$width[0] . " x " . $height[0] . " x " . $depth[0];
                   $attributes=array(
                                  'width' => $width[0],
                                  'height' => $height[0],
                                  'depth' => $depth[0],
                                  'comment' => "P2049, P2048, P5524"
                               );
	           $this->appendNewElement($oai, "dcterms:format", $text, $attributes );
        }
        elseif (count($width)==1 && count($height)==1) {
                   $text=$width[0] . " x " . $height[0] ;
                   $attributes=array(
                                  'width' => $width[0],
                                  'height' => $height[0],
                                  'comment' => "P2049, P2048"
                               );
	           $this->appendNewElement($oai, "dcterms:format", $text, $attributes );
        }

        // Record id = Wikidata id in bibo:url 
        if (isset($valuesAll["bibo:uri"])) {
           $wikidata_uri=$valuesAll["bibo:uri"];
           foreach ($wikidata_uri["values"] as $k=>$v) {
                   list($text, $attributes) = $this->formatValue($v); 
                   $attributes["comment"]=$wikidata_uri["alternate_comment"];
	           $this->appendNewElement($oai, "recordID", $text );
                   break;
           }
        }
    }

    public function getMetadataPrefix()
    {
        return self::METADATA_PREFIX;
    }

    public function getMetadataSchema()
    {
        return self::METADATA_SCHEMA;
    }

    public function getMetadataNamespace()
    {
        return self::METADATA_NAMESPACE;
    }
}
