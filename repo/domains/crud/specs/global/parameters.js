'use strict';

module.exports = {
	"ItemId": {
		"in": "path",
		"name": "item_id",
		"description": "The ID of the required Item",
		"required": true,
		"schema": {
			"type": "string",
			"pattern": "^Q[1-9]\\d{0,9}$"
		},
		"example": "Q24"
	},
	"PropertyId": {
		"in": "path",
		"name": "property_id",
		"description": "The ID of the required Property",
		"required": true,
		"schema": {
			"type": "string",
			"pattern": "^P[1-9]\\d{0,9}$"
		},
		"example": "P694"
	},
	"StatementId": {
		"in": "path",
		"name": "statement_id",
		"description": "The ID of a Statement",
		"required": true,
		"schema": {
			"type": "string",
			"pattern": "^(Q|q|P|p)[1-9]\\d{0,9}\\$.+$"
		},
		"example": { "$ref": '#/components/parameters/ItemStatementId/example' }
	},
	"ItemStatementId": {
		"in": "path",
		"name": "statement_id",
		"description": "The ID of a Statement on an Item",
		"required": true,
		"schema": {
			"type": "string",
			"pattern": "^(Q|q)[1-9]\\d{0,9}\\$.+$"
		},
		"example": "Q24$9966A1CA-F3F5-4B1D-A534-7CD5953169DA"
	},
	"PropertyStatementId": {
		"in": "path",
		"name": "statement_id",
		"description": "The ID of a Statement on a Property",
		"required": true,
		"schema": {
			"type": "string",
			"pattern": "^(P|p)[1-9]\\d{0,9}\\$.+$"
		},
		"example": "P694$B4C349A2-C504-4FC5-B7D5-8B781C719D71"
	},
	"LanguageCode": {
		"in": "path",
		"name": "language_code",
		"description": "The requested resource language",
		"required": true,
		"schema": {
			"type": "string",
			"pattern": "^[a-z]{2}[a-z0-9-]*$"
		},
		"example": "en"
	},
	"SiteId": {
		"in": "path",
		"name": "site_id",
		"description": "The ID of the required Site",
		"required": true,
		"schema": {
			"type": "string"
		},
		"example": "enwiki"
	},
	"ItemFields": {
		"in": "query",
		"name": "_fields",
		"description": "Comma-separated list of fields to include in each response object.",
		"required": false,
		"schema": {
			"type": "array",
			"items": {
				"type": "string",
				"enum": [ "type", "labels", "descriptions", "aliases", "statements", "sitelinks" ]
			}
		},
		"explode": false,
		"style": "form"
	},
	"PropertyFields": {
		"in": "query",
		"name": "_fields",
		"description": "Comma-separated list of fields to include in each response object.",
		"required": false,
		"schema": {
			"type": "array",
			"items": {
				"type": "string",
				"enum": [ "type", "data_type", "labels", "descriptions", "aliases", "statements" ]
			}
		},
		"explode": false,
		"style": "form"
	},
	"PropertyFilter": {
		"in": "query",
		"name": "property",
		"description": "Single Property ID to filter Statements by.",
		"required": false,
		"schema": {
			"type": "string",
			"pattern": "^P[1-9]\\d{0,9}$"
		},
		"style": "form",
		"example": "P1628"
	},
	"IfNoneMatch": {
		"name": "If-None-Match",
		"in": "header",
		"description": "Conditionally perform the request only if the resource has been modified since the specified entity revision numbers",
		"schema": {
			"type": "array",
			"items": {
				"type": "string",
				"pattern": "^(?:\".+\"|\\*)$"
			}
		},
		"example": [ "\"1276705620\"" ]
	},
	"IfModifiedSince": {
		"name": "If-Modified-Since",
		"in": "header",
		"description": "Conditionally perform the request only if the resource has been modified after the specified date",
		"schema": {
			"type": "string",
			"format": "http-date"
		},
		"example": "Sat, 06 Jun 2020 16:38:47 GMT"
	},
	"IfMatch": {
		"name": "If-Match",
		"in": "header",
		"description": "Conditionally perform the request only if the resource has not been modified since one of the specified entity revision numbers",
		"schema": {
			"type": "array",
			"items": {
				"type": "string",
				"pattern": "^(?:\".+\"|\\*)$"
			}
		},
		"example": [ "\"1276705620\"" ]
	},
	"IfUnmodifiedSince": {
		"name": "If-Unmodified-Since",
		"in": "header",
		"description": "Conditionally perform the request only if the resource has not been modified after the specified date",
		"schema": {
			"type": "string",
			"format": "http-date"
		},
		"example": "Sat, 06 Jun 2020 16:38:47 GMT"
	},
	"Authorization": {
		"name": "Authorization",
		"in": "header",
		"description": "Make authenticated request using a provided bearer token",
		"schema": {
			"type": "string"
		},
		"example": "Bearer mF_9.B5f-4.1JqM"
	}
};
