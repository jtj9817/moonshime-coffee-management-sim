---
trigger: model_decision
description: This rule should be triggered at the end of every implementation of a plan unless there's no code changes.
---

## When to Create Automated Test Scripts

After any implementation phase where code changes have been made, create standalone testing scripts instead of relying on repetitive `php artisan tinker` sessions or manual `artisan` commands. This is especially critical when:

- New services, repositories, or business logic have been implemented
- Code changes require verification before unit tests are written or updated
- Complex workflows need validation with specific data scenarios
- Multiple components interact and need integration verification
- Database operations need testing with realistic data patterns
- Existing seeders and factories need to be leveraged for realistic test data

## Planning Testing After Implementation

When a coding phase completes, follow this workflow:

1. **Identify what changed** - Which services, repositories, models were modified?
2. **Plan test scenarios** - What inputs, edge cases, and data states should be verified?
3. **Select data generation strategy** - Which factories and seeders will create realistic test data?
4. **Create the verification script** - Generate a standalone PHP script in `tests/` directory
5. **Execute and inspect** - Run the script, review console output, verify relationships
6. **Validate state restoration** - Confirm database returns to initial state
7. **Update unit tests** - Use insights from verification to improve test coverage

## Integration with Unit Testing

Automated verification scripts complement, not replace, unit tests:

**Use verification scripts for:**
- Quick validation after code changes
- Complex scenarios with multiple model interactions
- Integration point testing with realistic data
- Manual inspection of relationships and state
- Immediate feedback during development

**Use unit tests for:**
- Comprehensive edge case coverage
- CI/CD pipeline integration
- Regression protection
- Isolated component testing
- Long-term code quality assurance

**Recommended workflow:**
- Make code changes → Run verification script → Review output → Update unit tests → Commit all changes