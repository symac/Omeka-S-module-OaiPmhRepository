<?php
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
 * Class implementing metadata output for the required oai_dc metadata format.
 * oai_dc is output of the 15 unqualified Dublin Core fields.
 */
class OaiDcBnf extends OaiDc
{
    /**
     * Appends Dublin Core metadata.
     *
     * {@inheritDoc}
     */
    public function appendMetadata(DOMElement $metadataElement, ItemRepresentation $item)
    {
        $document = $metadataElement->ownerDocument;

        $oai = $document->createElementNS(self::METADATA_NAMESPACE, 'oai_dc:dc');
        $metadataElement->appendChild($oai);

        /* Must manually specify XML schema uri per spec, but DOM won't include
         * a redundant xmlns:xsi attribute, so we just set the attribute
         */
        $oai->setAttribute('xmlns:dc', self::DC_NAMESPACE_URI);
        $oai->setAttribute('xmlns:xsi', parent::XML_SCHEMA_NAMESPACE_URI);
        $oai->setAttribute('xsi:schemaLocation', self::METADATA_NAMESPACE . ' ' .
            self::METADATA_SCHEMA);

        /* Each of the 15 unqualified Dublin Core elements, in the order
         * specified by the oai_dc XML schema
         */
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
            'rights'
        ];

        // Specific for BnF
        if ( $item->resourceClass() != null ) {
            $item_type = $item->resourceClass()->localName();
            if ($item_type == "Livre") {
                $this->appendNewElement($oai, 'dc:type', "text");
                $this->appendNewElement($oai, 'dc:language', "fr");
            } else {
                $this->appendNewElement($oai, 'dc:type', "image");
            }
        }

        /* Must create elements using createElement to make DOM allow a
         * top-level xmlns declaration instead of wasteful and non-
         * compliant per-node declarations.
         */
        foreach ($localNames as $localName) {
            $term = 'dcterms:' . $localName;
            $values = $item->value($term, ['all' => true, 'default' => []]);

            if ($localName == 'rights') {
                // Specific BnF
                if (  ! empty($dcElementsRight) ) {
                    if (preg_match("#Public Domain mark#", $values[0])) {
                        $this->appendNewElement($oai, 'dc:rights', "domaine public")->setAttribute("xml:lang", "fre");
                        $this->appendNewElement($oai, 'dc:rights', "public domain")->setAttribute("xml:lang", "eng");
                        $this->appendNewElement($oai, 'dc:rights', "http://creativecommons.org/publicdomain/mark/1.0/");
                    } else {
                        $this->appendNewElement($oai, 'dc:rights', "conditions spécifiques d'utilisation")->setAttribute("xml:lang", "fre");
                        $this->appendNewElement($oai, 'dc:rights', "restricted use")->setAttribute("xml:lang", "eng");
                    }
                } else {
                    $this->appendNewElement($oai, 'dc:rights', "conditions spécifiques d'utilisation")->setAttribute("xml:lang", "fre");
                    $this->appendNewElement($oai, 'dc:rights', "restricted use")->setAttribute("xml:lang", "eng");
                }
            } else {
                $values = $this->filterValues($item, $term, $values);
                foreach ($values as $value) {
                    $this->appendNewElement($oai, 'dc:' . $localName, (string) $value);
                }
            }

            // Specific BnF
            if($localName == 'relation')
            {
                $num_vig = ($item->value("1886:pageVignette",  ['all' => true]) ?: []);
                if ( ! empty($num_vig)) {
                    $num_vig = $num_vig[0]->__toString();
                    $num_vig -= 1;
                } else {
                    $num_vig = 0;
                }
                $media = $item->media();
                $this->appendNewElement($oai,'dc:relation', 'vignette : ' . $media[$num_vig]->thumbnailUrl('medium'));
            }
        }

        $appendIdentifier = $this->singleIdentifier($item);
        if ($appendIdentifier) {
            $this->appendNewElement($oai, 'dc:identifier', $appendIdentifier);
        }

        // Also append an identifier for each file
        if ($this->settings->get('oaipmhrepository_expose_media', false)) {
            foreach ($item->media() as $media) {
                $this->appendNewElement($oai, 'dc:identifier', $media->originalUrl());
            }
        }
    }
}
