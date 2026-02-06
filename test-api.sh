#!/bin/bash

# Automated API test suite for the Family Task API.
# Tests all 7 endpoints with curl and colored output.

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Configuration via environment variables
API_URL="${API_URL:-http://localhost:8000}"
ADMIN_PIN="${ADMIN_PIN:-1234}"
CHILD_PIN="${CHILD_PIN:-0000}"

# Test counters
TESTS_PASSED=0
TESTS_FAILED=0
TESTS_TOTAL=0

# Usage info
if [ "$1" = "--help" ] || [ "$1" = "-h" ]; then
    echo "Usage: ./test-api.sh [OPTIONS]"
    echo ""
    echo "Environment Variables:"
    echo "  API_URL      Base URL for API (default: http://localhost:8000)"
    echo "  ADMIN_PIN    Admin PIN for testing (default: 1234)"
    echo "  CHILD_PIN    Child PIN for testing (default: 0000)"
    echo ""
    echo "Examples:"
    echo "  ./test-api.sh"
    echo "  API_URL=http://example.com ./test-api.sh"
    echo "  API_URL=http://example.com ADMIN_PIN=9999 ./test-api.sh"
    exit 0
fi

# Helper: run a test and check the response body against a pattern
function test_endpoint() {
    local test_name="$1"
    local response_body="$2"
    local expected_pattern="$3"

    TESTS_TOTAL=$((TESTS_TOTAL + 1))

    if echo "$response_body" | grep -qE "$expected_pattern"; then
        echo -e "  ${GREEN}✓ PASSED${NC} — $test_name"
        TESTS_PASSED=$((TESTS_PASSED + 1))
        return 0
    else
        echo -e "  ${RED}✗ FAILED${NC} — $test_name"
        echo -e "  ${RED}Expected pattern:${NC} $expected_pattern"
        echo -e "  ${RED}Got:${NC} $response_body"
        TESTS_FAILED=$((TESTS_FAILED + 1))
        return 1
    fi
}

echo ""
echo -e "${BLUE}======================================${NC}"
echo -e "${BLUE} API TEST SUITE — Family Task API${NC}"
echo -e "${BLUE}======================================${NC}"
echo ""
echo -e "Base URL: ${YELLOW}$API_URL${NC}"
echo ""

# --------------------------------------------------
# 1. Admin PIN Check
# --------------------------------------------------
echo -e "${YELLOW}1. Admin Authentication${NC}"
ADMIN_RESPONSE=$(curl -s -X POST "$API_URL/api/pin/check" \
    -H "Content-Type: application/json" \
    -d "{\"pin\":\"$ADMIN_PIN\"}")

test_endpoint "Admin PIN returns success + role=admin" \
    "$ADMIN_RESPONSE" \
    '"success":true.*"role":"admin"'

ADMIN_TOKEN=$(echo "$ADMIN_RESPONSE" | grep -o '"token":"[^"]*"' | cut -d'"' -f4)
if [ -n "$ADMIN_TOKEN" ]; then
    echo -e "  Token: ${ADMIN_TOKEN:0:30}..."
fi
echo ""

# --------------------------------------------------
# 2. Child PIN Check
# --------------------------------------------------
echo -e "${YELLOW}2. Child Authentication${NC}"
CHILD_RESPONSE=$(curl -s -X POST "$API_URL/api/pin/check" \
    -H "Content-Type: application/json" \
    -d "{\"pin\":\"$CHILD_PIN\"}")

test_endpoint "Child PIN returns success + role=child" \
    "$CHILD_RESPONSE" \
    '"success":true.*"role":"child"'

CHILD_TOKEN=$(echo "$CHILD_RESPONSE" | grep -o '"token":"[^"]*"' | cut -d'"' -f4)
CHILD_ID=$(echo "$CHILD_RESPONSE" | grep -o '"childId":[0-9]*' | cut -d':' -f2)
if [ -n "$CHILD_TOKEN" ]; then
    echo -e "  Token: ${CHILD_TOKEN:0:30}..."
    echo -e "  Child ID: $CHILD_ID"
fi
echo ""

# --------------------------------------------------
# 3. Invalid PIN Check (should fail)
# --------------------------------------------------
echo -e "${YELLOW}3. Invalid PIN (negative test)${NC}"
INVALID_RESPONSE=$(curl -s -X POST "$API_URL/api/pin/check" \
    -H "Content-Type: application/json" \
    -d '{"pin":"9999"}')

test_endpoint "Invalid PIN returns success=false" \
    "$INVALID_RESPONSE" \
    '"success":false'
echo ""

# --------------------------------------------------
# 4. GET /api/admin/children
# --------------------------------------------------
echo -e "${YELLOW}4. GET /api/admin/children${NC}"
if [ -z "$ADMIN_TOKEN" ]; then
    echo -e "  ${RED}✗ SKIPPED${NC} — No admin token"
    TESTS_TOTAL=$((TESTS_TOTAL + 1))
    TESTS_FAILED=$((TESTS_FAILED + 1))
else
    CHILDREN_RESPONSE=$(curl -s -X GET "$API_URL/api/admin/children" \
        -H "Authorization: Bearer $ADMIN_TOKEN")

    test_endpoint "Returns children list" \
        "$CHILDREN_RESPONSE" \
        '"success":true.*"data":\['
    echo -e "  Response: $CHILDREN_RESPONSE"
fi
echo ""

# --------------------------------------------------
# 5. GET /api/admin/tasks
# --------------------------------------------------
echo -e "${YELLOW}5. GET /api/admin/tasks${NC}"
if [ -z "$ADMIN_TOKEN" ]; then
    echo -e "  ${RED}✗ SKIPPED${NC} — No admin token"
    TESTS_TOTAL=$((TESTS_TOTAL + 1))
    TESTS_FAILED=$((TESTS_FAILED + 1))
else
    TASKS_RESPONSE=$(curl -s -X GET "$API_URL/api/admin/tasks" \
        -H "Authorization: Bearer $ADMIN_TOKEN")

    test_endpoint "Returns tasks list" \
        "$TASKS_RESPONSE" \
        '"success":true.*"data":\['
fi
echo ""

# --------------------------------------------------
# 6. POST /api/admin/tasks (Create Task)
# --------------------------------------------------
echo -e "${YELLOW}6. POST /api/admin/tasks (Create Task)${NC}"
if [ -z "$ADMIN_TOKEN" ] || [ -z "$CHILD_ID" ]; then
    echo -e "  ${RED}✗ SKIPPED${NC} — Missing admin token or child ID"
    TESTS_TOTAL=$((TESTS_TOTAL + 1))
    TESTS_FAILED=$((TESTS_FAILED + 1))
else
    DUE_DATE=$(date -u -d "+1 day" +"%Y-%m-%dT15:00:00+00:00" 2>/dev/null || date -u -v+1d +"%Y-%m-%dT15:00:00+00:00")

    CREATE_RESPONSE=$(curl -s -X POST "$API_URL/api/admin/tasks" \
        -H "Authorization: Bearer $ADMIN_TOKEN" \
        -H "Content-Type: application/json" \
        -d "{\"childId\":$CHILD_ID,\"title\":\"Test Aufgabe\",\"description\":\"Automatischer Test\",\"points\":5,\"dueDate\":\"$DUE_DATE\"}")

    test_endpoint "Task created successfully" \
        "$CREATE_RESPONSE" \
        '"success":true.*"id":[0-9]'

    TASK_ID=$(echo "$CREATE_RESPONSE" | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)
    if [ -n "$TASK_ID" ]; then
        echo -e "  Created Task ID: $TASK_ID"
    fi
fi
echo ""

# --------------------------------------------------
# 7. GET /api/child/{childId}/tasks
# --------------------------------------------------
echo -e "${YELLOW}7. GET /api/child/$CHILD_ID/tasks${NC}"
if [ -z "$CHILD_TOKEN" ] || [ -z "$CHILD_ID" ]; then
    echo -e "  ${RED}✗ SKIPPED${NC} — Missing child token or ID"
    TESTS_TOTAL=$((TESTS_TOTAL + 1))
    TESTS_FAILED=$((TESTS_FAILED + 1))
else
    CHILD_TASKS_RESPONSE=$(curl -s -X GET "$API_URL/api/child/$CHILD_ID/tasks" \
        -H "Authorization: Bearer $CHILD_TOKEN")

    test_endpoint "Returns child's task list" \
        "$CHILD_TASKS_RESPONSE" \
        '"success":true.*"data":\['
fi
echo ""

# --------------------------------------------------
# 8. POST /api/child/tasks/{id}/complete
# --------------------------------------------------
echo -e "${YELLOW}8. POST /api/child/tasks/$TASK_ID/complete${NC}"
if [ -z "$CHILD_TOKEN" ] || [ -z "$TASK_ID" ]; then
    echo -e "  ${RED}✗ SKIPPED${NC} — Missing child token or task ID"
    TESTS_TOTAL=$((TESTS_TOTAL + 1))
    TESTS_FAILED=$((TESTS_FAILED + 1))
else
    COMPLETE_RESPONSE=$(curl -s -X POST "$API_URL/api/child/tasks/$TASK_ID/complete" \
        -H "Authorization: Bearer $CHILD_TOKEN" \
        -H "Content-Type: application/json" \
        -d "{\"pin\":\"$CHILD_PIN\"}")

    test_endpoint "Task completed + points awarded" \
        "$COMPLETE_RESPONSE" \
        '"success":true.*"newPoints":[0-9]'
fi
echo ""

# --------------------------------------------------
# 9. GET /api/child/{childId}/points
# --------------------------------------------------
echo -e "${YELLOW}9. GET /api/child/$CHILD_ID/points${NC}"
if [ -z "$CHILD_TOKEN" ] || [ -z "$CHILD_ID" ]; then
    echo -e "  ${RED}✗ SKIPPED${NC} — Missing child token or ID"
    TESTS_TOTAL=$((TESTS_TOTAL + 1))
    TESTS_FAILED=$((TESTS_FAILED + 1))
else
    POINTS_RESPONSE=$(curl -s -X GET "$API_URL/api/child/$CHILD_ID/points" \
        -H "Authorization: Bearer $CHILD_TOKEN")

    test_endpoint "Returns point balance" \
        "$POINTS_RESPONSE" \
        '"success":true.*"points":[0-9]'
fi
echo ""

# --------------------------------------------------
# Summary
# --------------------------------------------------
echo -e "${BLUE}======================================${NC}"
echo -e "${BLUE} TEST SUMMARY${NC}"
echo -e "${BLUE}======================================${NC}"
echo -e "Total:  $TESTS_TOTAL"
echo -e "${GREEN}Passed: $TESTS_PASSED${NC}"
echo -e "${RED}Failed: $TESTS_FAILED${NC}"
echo ""

if [ "$TESTS_FAILED" -eq 0 ]; then
    echo -e "${GREEN}✓ ALL TESTS PASSED!${NC}"
    exit 0
else
    echo -e "${RED}✗ SOME TESTS FAILED${NC}"
    exit 1
fi
