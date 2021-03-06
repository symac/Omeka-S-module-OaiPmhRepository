<?php
/**
 * @author John Flatness
 * @copyright Copyright 2012 John Flatness
 * @copyright BibLibre, 2016
 * @copyright Daniel Berthereau, 2014-2018
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */
namespace OaiPmhRepository\OaiPmh\Metadata;

use DOMElement;
use Omeka\Api\Representation\ItemRepresentation;

/**
 * Class implementing METS metadata output format.
 *
 * @link https://www.loc.gov/standards/mets/
 */
class Mets extends AbstractMetadata
{
    /** OAI-PMH metadata prefix */
    const METADATA_PREFIX = 'mets';

    /** XML namespace for output format */
    const METADATA_NAMESPACE = 'http://www.loc.gov/METS/';

    /** XML schema for output format */
    const METADATA_SCHEMA = 'http://www.loc.gov/standards/mets/mets.xsd';

    /** XML namespace for unqualified Dublin Core */
    const DC_NAMESPACE_URI = 'http://purl.org/dc/elements/1.1/';

    /** XML namespace for Dublin Core */
    const DCTERMS_NAMESPACE_URI = 'http://purl.org/dc/terms/';

    /**
     * Appends METS metadata.
     *
     * {@inheritDoc}
     */
    public function appendMetadata(DOMElement $metadataElement, ItemRepresentation $item)
    {
        $document = $metadataElement->ownerDocument;
        $mets = $document->createElementNS(self::METADATA_NAMESPACE, 'mets');
        $metadataElement->appendChild($mets);

        /* Must manually specify XML schema uri per spec, but DOM won't include
         * a redundant xmlns:xsi attribute, so we just set the attribute
         */
        $mets->setAttribute('xmlns:xsi', self::XML_SCHEMA_NAMESPACE_URI);
        $mets->setAttribute('xsi:schemaLocation', self::METADATA_NAMESPACE
            . ' ' . self::METADATA_SCHEMA);

        $mets->setAttribute('xmlns:xlink', 'http://www.w3.org/1999/xlink');

        $metadataSection = $this->appendNewElement($mets, 'dmdSec');
        $itemDmdId = 'dmd-' . $item->id();
        $metadataSection->setAttribute('ID', $itemDmdId);
        $dataWrap = $this->appendNewElement($metadataSection, 'mdWrap');

        $itemDataFormat = $this->settings->get('oaipmhrepository_mets_data_item');
        switch ($itemDataFormat) {
            case 'dc':
            default:
                $this->mdtypeDc($dataWrap, $item);
                break;
            case 'dcterms':
                $this->mdtypeDcterms($dataWrap, $item);
                break;
            // case 'mods':
            //    break;
        }

        $fileIds = [];
        if ($this->settings->get('oaipmhrepository_expose_media', false)) {
            $mediaList = $item->media();
            if (count($mediaList)) {
                $mediaDataFormat = $this->settings->get('oaipmhrepository_mets_data_media');

                $fileSection = $this->appendNewElement($mets, 'fileSec');
                $fileGroup = $this->appendNewElement($fileSection, 'fileGrp');
                $fileGroup->setAttribute('USE', 'ORIGINAL');

                foreach ($mediaList as $media) {
                    $fileDmdId = 'dmd-file-' . $media->id();
                    $fileId = 'file-' . $media->id();
                    $fileIds[] = $fileId;

                    $fileElement = $this->appendNewElement($fileGroup, 'file');
                    $fileElement->setAttribute('xmlns:dc', self::DC_NAMESPACE_URI);
                    $fileElement->setAttribute('ID', $fileId);
                    //$fileElement->setAttribute('MIMETYPE', $file->mime_type);
                    $fileElement->setAttribute('CHECKSUM', $media->sha256());
                    $fileElement->setAttribute('CHECKSUMTYPE', 'SHA-256');
                    $fileElement->setAttribute('DMDID', $fileDmdId);

                    $location = $this->appendNewElement($fileElement, 'FLocat');

                    $location->setAttribute('LOCTYPE', 'URL');
                    $location->setAttribute('xlink:type', 'simple');
                    $location->setAttribute('xlink:title', $media->filename());
                    $location->setAttribute('xlink:href', $media->originalUrl());

                    $fileContentMetadata = $this->appendNewElement($mets, 'dmdSec');
                    $fileContentMetadata->setAttribute('ID', $fileDmdId);

                    $fileDataWrap = $this->appendNewElement($fileContentMetadata, 'mdWrap');
                    switch ($mediaDataFormat) {
                        case 'dc':
                        default:
                            $this->mdtypeDc($fileDataWrap, $media);
                            break;
                        case 'dcterms':
                            $this->mdtypeDcterms($fileDataWrap, $media);
                            break;
                            // case 'mods':
                            //    break;
                    }
                }
            }
        }

        $structMap = $this->appendNewElement($mets, 'structMap');
        $topDiv = $this->appendNewElement($structMap, 'div');
        $topDiv->setAttribute('DMDID', $itemDmdId);
        foreach ($fileIds as $id) {
            $fptr = $this->appendNewElement($topDiv, 'fptr');
            $fptr->setAttribute('FILEID', $id);
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

    protected function mdtypeDc($dataWrap, $resource)
    {
        $dataWrap->setAttribute('MDTYPE', 'DC');
        $dataXml = $this->appendNewElement($dataWrap, 'xmlData');
        $dataXml->setAttribute('xmlns:dc', self::DC_NAMESPACE_URI);

        $localNames = [
            'title',
            'creator',
            'subject',
            'description',
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
        ];

        foreach ($localNames as $localName) {
            $term = 'dcterms:' . $localName;
            $values = $resource->value($term, ['all' => true, 'default' => []]);
            $values = $this->filterValues($resource, $term, $values);
            foreach ($values as $value) {
                $this->appendNewElement($dataXml, 'dc:' . $localName, (string) $value);
            }
        }
    }

    protected function mdtypeDcterms($dataWrap, $resource)
    {
        $dataWrap->setAttribute('MDTYPE', 'DC');
        $dataWrap->setAttribute('MDTYPEVERSION', 'DCMI Metadata Terms');
        $dataXml = $this->appendNewElement($dataWrap, 'xmlData');
        $dataXml->setAttribute('xmlns:dcterms', self::DCTERMS_NAMESPACE_URI);

        // Each of the 55 Dublin Core terms, in the Omeka order.
        $localNames = [
            // Dublin Core Elements.
            'title',
            'creator',
            'subject',
            'description',
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

        foreach ($localNames as $localName) {
            $term = 'dcterms:' . $localName;
            $values = $resource->value($term, ['all' => true, 'default' => []]);
            $values = $this->filterValues($resource, $term, $values);
            foreach ($values as $value) {
                $this->appendNewElement($dataXml, $term, (string) $value);
            }
        }
    }
}
