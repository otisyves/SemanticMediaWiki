<?php

namespace SMW\Elastic\QueryEngine\DescriptionInterpreters;

use SMW\Elastic\QueryEngine\QueryBuilder;
use SMW\Query\Language\ConceptDescription;
use SMW\Query\Language\Description;
use SMW\Query\Language\Conjunction;
use SMW\Query\Language\Disjunction;
use SMW\ApplicationFactory;
use SMW\DIWikiPage;
use SMW\DIProperty;
use SMW\Options;

/**
 * @license GNU GPL v2+
 * @since 3.0
 *
 * @author mwjames
 */
class ConceptDescriptionInterpreter {

	/**
	 * @var QueryBuilder
	 */
	private $queryBuilder;

	/**
	 * @var Options
	 */
	private $options;

	/**
	 * @var QueryParser
	 */
	private $queryParser;

	/**
	 * @since 3.0
	 *
	 * @param QueryBuilder $queryBuilder
	 * @param Options $options
	 */
	public function __construct( QueryBuilder $queryBuilder, Options $options ) {
		$this->queryBuilder = $queryBuilder;
		$this->options = $options;
		$this->queryParser = ApplicationFactory::getInstance()->newQueryParser();
	}

	/**
	 * @since 3.0
	 *
	 * @param ConceptDescription $description
	 *
	 * @return array
	 */
	public function interpretDescription( ConceptDescription $description, $isConjunction = false ) {

		$this->queryBuilder->addDescriptionLog( $description);

		$concept = $description->getConcept();

		$value = $this->queryBuilder->getStore()->getPropertyValues(
			$concept,
			new DIProperty( '_CONC' )
		);

		if ( $value === null || $value === array() ) {
			return [];
		}

		$value = end( $value );

		$description = $this->queryParser->getQueryDescription(
			$value->getConceptQuery()
		);

		if ( $this->hasCircularConceptDescription( $description, $concept ) ) {
			return [];
		}

		$params = $this->queryBuilder->interpretDescription(
			$description,
			$isConjunction
		);

		$concept->setId(
			$this->queryBuilder->getID( $concept )
		);

		$termsLookup = $this->queryBuilder->getTermsLookup();

		// Using the terms lookup to prefetch IDs from the lookup index
		if ( $this->options->safeGet( 'concept.terms.lookup' ) ) {
			$params = $termsLookup->lookupConcept(
				$concept,
				$description,
				$params
			);

			$this->queryBuilder->addQueryInfo( $termsLookup->getQueryInfo() );
		}

		return $params;
	}

	private function hasCircularConceptDescription( $description, $concept ) {

		if ( $description instanceof ConceptDescription ) {
			if ( $description->getConcept()->equals( $concept ) ) {
				$this->queryBuilder->addError( [ 'smw-query-condition-circular', $description->getQueryString() ] );
				return true;
			}
		}

		if ( $description instanceof Conjunction || $description instanceof Disjunction ) {
			foreach ( $description->getDescriptions() as $desc ) {
				if ( $this->hasCircularConceptDescription( $desc, $concept ) ) {
					return true;
				}
			}
		}

		return false;
	}

}
