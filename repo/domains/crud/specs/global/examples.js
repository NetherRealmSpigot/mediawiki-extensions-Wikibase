'use strict';

module.exports = {
	"InvalidValueExample": {
		"value": {
			"code": "invalid-value",
			"message": "Invalid value at '{json_pointer}'",
			"context": { "path": "{json_pointer}" }
		}
	},
	"InvalidKeyExample": {
		"value": {
			"code": "invalid-key",
			"message": "Invalid key '{key}' in '{json_pointer_to_parent}'",
			"context": { "path": "{json_pointer_to_parent}", "key": "{key}" }
		}
	},
	"MissingFieldExample": {
		"value": {
			"code": "missing-field",
			"message": "Required field missing",
			"context": {
				"path": "{json_pointer_to_parent}",
				"field": "{missing_field}"
			}
		}
	},
	"ValueTooLongExample": {
		"value": {
			"code": "value-too-long",
			"message": "The input value is too long",
			"context": {
				"path": "{json_pointer_to_element}",
				"limit": "{configured_limit}"
			}
		}
	},
	"DataPolicyViolationExample": {
		"value": {
			"code": "data-policy-violation",
			"message": "Edit violates data policy",
			"context": {
				"violation": "{violation_code}",
				"violation_context": {
					"some": "context"
				}
			}
		}
	},
	"PatchResultValueTooLongExample": {
		"value": {
			"code": "patch-result-value-too-long",
			"message": "Patched value is too long",
			"context": {
				"path": "{json_pointer_to_patched_element}",
				"limit": "{configured_limit}"
			}
		}
	},
	"InvalidQueryParameterExample": {
		"value": {
			"code": "invalid-query-parameter",
			"message": "Invalid query parameter: '{parameter}'",
			"context": { "parameter": "{parameter}" }
		}
	},
	"InvalidPathParameterExample": {
		"value": {
			"code": "invalid-path-parameter",
			"message": "Invalid path parameter: '{path_parameter}'",
			"context": { "parameter": "{path_parameter}" }
		}
	},
	"ResourceTooLargeExample": {
		"value": {
			"code": "resource-too-large",
			"message": "Edit resulted in a resource that exceeds the size limit of {configured_limit}",
			"context": { "limit": "configured_limit_as_int" }
		}
	},
	"ReferencedResourceNotFoundExample": {
		"value": {
			"code": "referenced-resource-not-found",
			"message": "The referenced resource does not exist",
			"context": { "path": "{json_pointer}" }
		}
	},
	"CannotModifyReadOnlyValue": {
		"value": {
			"code": "cannot-modify-read-only-value",
			"message": "The input value cannot be modified",
			"context": {
				"path": "{readonly_value_pointer}"
			}
		}
	},
	"PatchResultModifiedReadOnlyValue": {
		"value": {
			"code": "patch-result-modified-read-only-value",
			"message": "Read only value in patch result cannot be modified",
			"context": {
				"path": "{json_pointer_to_readonly_value}"
			}
		}
	},
	"PatchTestFailedExample": {
		"value": {
			"code": "patch-test-failed",
			"message": "Test operation in the provided patch failed",
			"context": {
				"path": "{json_pointer_to_patch_operation}",
				"actual_value": "actual value"
			}
		}
	},
	"PatchTargetNotFoundExample": {
		"value": {
			"code": "patch-target-not-found",
			"message": "Target not found on resource",
			"context": {
				"path": "{json_pointer_to_target_in_patch}"
			}
		}
	},
	"RedirectedItemExample": {
		"value": {
			"code": "redirected-item",
			"message": "Item {item_id} has been redirected to {redirect_target_id}",
			"context": {
				"redirect_target": "{redirect_target_id}"
			}
		}
	},
	"ResourceNotFoundExample": {
		"value": {
			"code": "resource-not-found",
			"message": "The requested resource does not exist",
			"context": {
				"resource_type": "{resource_type}"
			}
		}
	},
	"PatchResultResourceNotFoundExample": {
		"value": {
			"code": "patch-result-referenced-resource-not-found",
			"message": "The referenced resource does not exist",
			"context": {
				"path": "{json_pointer_to_missing_resource_in_patch_result}",
				"value": "{value}"
			}
		}
	},
	"PatchResultInvalidKeyExample": {
		"value": {
			"code": "patch-result-invalid-key",
			"message": "Invalid key in patch result",
			"context": { "path": "{json_pointer_to_parent_in_patch_result}", "key": "{key}" }
		}
	},
	"PatchResultInvalidValueExample": {
		"value": {
			"code": "patch-result-invalid-value",
			"message": "Invalid value in patch result",
			"context": {
				"value": "{value}",
				"path": "{path}"
			}
		}
	},
	"PatchResultMissingFieldExample": {
		"value": {
			"code": "patch-result-missing-field",
			"message": "Required field missing in patch result",
			"context": { "path": "{json_pointer_to_parent}", "field": "{missing_field}" }
		}
	},
	"PatchedStatementGroupPropertyIdMismatchExample": {
		"value": {
			"code": "patched-statement-group-property-id-mismatch",
			"message": "Statement's Property ID does not match the Statement group key",
			"context": {
				"path": "{property_id_key}/{index}/property/id",
				"statement_group_property_id": "{property_id_key}",
				"statement_property_id": "{property_id_value}"
			}
		}
	},
	"ItemStatementIdMismatchExample": {
		"value": {
			"code": "item-statement-id-mismatch",
			"message": "IDs of the Item and the Statement do not match",
			"context": {
				"item_id": "{item_id}",
				"statement_id": "{statement_id}"
			}
		}
	},
	"PropertyStatementIdMismatchExample": {
		"value": {
			"code": "property-statement-id-mismatch",
			"message": "IDs of the Property and the Statement do not match",
			"context": {
				"property_id": "{property_id}",
				"statement_id": "{statement_id}"
			}
		}
	},
	"StatementGroupPropertyIdMismatch": {
		"value": {
			"code": "statement-group-property-id-mismatch",
			"message": "Statement's Property ID does not match the Statement group key",
			"context": {
				"path": "{property_id_key}/{index}/property/id",
				"statement_group_property_id": "{property_id_key}",
				"statement_property_id": "{property_id_value}"
			}
		}
	},
	"PermissionDeniedExample": {
		"value": {
			"code": "permission-denied",
			"message": "Access to resource is denied",
			"context": {
				"denial_reason": "{reason_code}",
				"denial_context": "{additional_context}"
			}
		}
	}
};
