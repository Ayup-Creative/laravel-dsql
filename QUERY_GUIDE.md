# DSQL User Guide: Creating Advanced Reports

Welcome to the **Domain-Specific Query Language (DSQL)**. This guide is designed for users who want to create custom reports, search data, or export CSVs using a simple yet powerful text-based language.

## What is DSQL?

DSQL is a human-readable way to tell the system exactly what information you want to see and how to filter it. Think of it like writing a very precise sentence to describe your report.

---

## Step 1: Choosing a "Base Model"

Before you write a query, you need to decide on a **Base Model**. This is the "starting point" or the main subject of your report.

### How to decide:
*   **What is the main thing you are counting or listing?**
    *   If you want a list of **Customers**, your base model is "Customer".
    *   If you want a list of **Sales Receipts**, your base model is "Receipt".
    *   If you want to see individual **Products**, your base model is "Product".

**Why it matters:** Your choice of base model determines which fields are available directly (like `[name]` or `[price]`) and which ones you have to look up through relationships (like `[customer.name]`).

---

## Step 2: Selecting Your Columns

You can choose exactly which columns appear in your report using the `SELECT` keyword.

### Simple Selection
To pick specific columns, list them in square brackets:
`SELECT [receipt_number], [total_amount]`

### Renaming Columns (Aliasing)
If you want a column to have a friendlier header in your CSV, use the `AS` keyword:
`SELECT [receipt_number] AS "ID", [total_amount] AS "Total Paid"`

### Calculating New Values
You can perform math directly in your selection:
`SELECT [price] * 1.2 AS "Price with VAT"`

---

## Step 3: Filtering Your Data

The `WHERE` part of your query tells the system which records to include.

### Basic Filters
Use a colon `:` followed by an operator:
*   `[status]:equals"active"` — Exactly matches "active".
*   `[price]:gt 100` — Price is **G**reater **T**han 100.
*   `[name]:contains"John"` — Name has "John" anywhere in it.
*   `[category]:in(Electronics, Home)` — Matches any value in the list.

### Logic (AND, OR, NOT)
Combine multiple rules:
`[status]:equals"paid" AND [total_amount]:gt 500`
`[category]:equals"Software" OR [category]:equals"Hardware"`

### Relationship Filters
You can filter based on related information:
`[customer.city]:equals"London"` (Finds all records where the associated customer lives in London).

---

## Advanced Features

### Arithmetic Operators
You can use standard math: `+` (add), `-` (subtract), `*` (multiply), `/` (divide).
Example: `SELECT [price] - [discount] AS "Final Price"`

### Aggregates (Counting & Checking)
You can count related items or check if they exist:
*   `SELECT COUNT([logs]) AS "Activity Count"` — Counts how many logs a product has.
*   `WHERE EXISTS([receipts])` — Only shows items that have at least one receipt.

### Dynamic Date Values
Instead of typing today's date, you can use built-in functions that update automatically:
*   `now()` — The current date and time.
*   `today()` — The start of today.
*   `yesterday()` — The start of yesterday.

You can even adjust them:
*   `[created_at]:gt now()->subDays(7)` — Created in the last 7 days.
*   `[created_at]:between(now()->startOfMonth(), now()->endOfMonth())` — Everything from the current month.

---

## Summary of Common Tools

| Tool | Usage Example | Purpose |
| :--- | :--- | :--- |
| **SELECT** | `SELECT [name]` | Chooses which columns to show. |
| **AS** | `[price] AS "Cost"` | Gives a column a custom header name. |
| **WHERE** | `WHERE [price]:gt 10` | Filters the list of results. |
| **SORT** | `SORT(price, desc)` | Orders the results (highest to lowest). |
| **LIMIT** | `LIMIT 10` | Only shows the first 10 results. |

## Pro Tips
1.  **Quotes:** Always wrap text values in double quotes, like `"active"`. Numbers don't need quotes.
2.  **Brackets:** Use square brackets `[ ]` for column names.
3.  **Parentheses:** Use `( )` to group logic, like `([price]:gt 100 OR [status]:equals"urgent")`.
