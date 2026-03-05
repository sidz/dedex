<?php

/*
 * The MIT License
 *
 * Copyright 2020 Mickaël Arcos <miqwit>.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace DedexBundle\Simplifiers;

use DateTimeImmutable;
use DedexBundle\Entity\Ern382\DealType;
use Exception;
use Throwable;

/**
 * A Simple deal.
 * Assumption: There is one deal per release
 * Assumption: There is one DealTerm per deal
 * Assumption: There is one validity period
 * Assumption: There is zero or one start date and end date
 *
 * @author Mickaël Arcos <miqwit>
 */
class SimpleDeal extends SimpleEntity {
	/**
	 *
	 * @var DealType
	 */
	private $ddexDeal;

	/**
	 * Version string as detected by ErnParserController.
	 *
	 * @var string
	 */
	private $version;

	/**
	 *
	 * @var array
	 */
	private $commercialModelTypes = [];

	/**
	 *
	 * @var array
	 */
	private $useTypes = [];

	/**
	 *
	 * @var array
	 */
	private $distributionChannelTypes = [];

	/**
	 *
	 * @var array
	 */
	private $territories = [];

	/**
	 * Populate all commercial model types, use types and distribution channel
	 * types from provided ddex deal.
	 *
	 * Considers only one deal.
	 *
	 * @param $ddexDeal
	 * @param string $version version string as detected by ErnParserController
	 */
	public function __construct($ddexDeal, string $version) {
		$this->version = $version;

		/* @var $deal DealType */
		$deal = $ddexDeal->getDeal()[0];
		$this->ddexDeal = $deal;

		// Get all commercial model types
		foreach ($deal->getDealTerms()->getCommercialModelType() as $cmt) {
			$this->commercialModelTypes[] = $this->getUserDefinedValue($cmt);
		}

		if ($this->isVersion4x($version)) {
			// ERN 4.x: UseType is directly on DealTerms, no Usage wrapper
			try {
				foreach ($deal->getDealTerms()->getUseType() as $usetype) {
					$this->useTypes[] = $this->getUserDefinedValue($usetype);
				}
			} catch (Throwable $ex) {
				// no use types
			}
		} else {
			// ERN 3.x: UseType and DistributionChannelType are nested under Usage
			try {
				foreach ($deal->getDealTerms()->getUsage() as $usage) {
					foreach ($usage->getUseType() as $usetype) {
						$this->useTypes[] = $this->getUserDefinedValue($usetype);
					}
					foreach ($usage->getDistributionChannelType() as $distribchanneltype) {
						$this->distributionChannelTypes[] = $this->getUserDefinedValue($distribchanneltype);
					}
				}
			} catch (Throwable $ex) {
				// no use types
			}
		}

		// Territory codes
		foreach ($deal->getDealTerms()->getTerritoryCode() as $territory) {
			$this->territories[] = is_object($territory) ? (string) $territory : $territory;
		}
	}
	
	/**
	 * Assumption: only one validity period.
	 * 
	 * @return DateTimeImmutable|null
	 */
	public function getStartDate(): ?DateTimeImmutable {
		try {
			return DateTimeImmutable::createFromFormat("Y-m-d", 
							$this->ddexDeal->getDealTerms()->getValidityPeriod()[0]->getStartDate()->value()
			);
		} catch (Exception $ex) {
			return null;
		} catch (Throwable $ex) {
			return null;
		}
	}
	
	/**
	 * Assumption: only one validity period.
	 * 
	 * @return DateTimeImmutable|null
	 */
	public function getEndDate(): ?DateTimeImmutable {
		try {
			return DateTimeImmutable::createFromFormat("Y-m-d", 
							$this->ddexDeal->getDealTerms()->getValidityPeriod()[0]->getEndDate()->value()
			);
		} catch (Exception $ex) {
			return null;
		} catch (Throwable $ex) {
			return null;
		}
	}
	
	/**
	 * @param string $cmt
	 * @return $this
	 */
	public function addCommercialModelType(string $cmt) {
		$this->commercialModelTypes[] = $cmt;
		return $this;
	}
	
	/**
	 * @return array
	 */
	public function getCommercialModelTypes(): array {
		return $this->commercialModelTypes;
	}
	
	/**
	 * @param string $usetype
	 * @return $this
	 */
	public function addUseType(string $usetype) {
		$this->useTypes[] = $usetype;
		return $this;
	}
	
	/**
	 * @return array
	 */
	public function getUseTypes(): array {
		return $this->useTypes;
	}
	
	/**
	 * @param string $dct
	 * @return $this
	 */
	public function addDistributionChannelType(string $dct) {
		$this->distributionChannelTypes[] = $dct;
		return $this;
	}
	
	/**
	 * @return array
	 */
	public function getDistributionChannelTypes(): array {
		return $this->distributionChannelTypes;
	}
	
	/**
	 * @param string $territory
	 * @return $this
	 */
	public function addTerritory(string $territory) {
		$this->territories[] = $territory;
		return $this;
	}
	
	/**
	 * @return array
	 */
	public function getTerritories(): array {
		return $this->territories;
	}
}
