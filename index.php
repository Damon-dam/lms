<?php
session_start();
require_once "config/db.php";

// Get statistics for homepage
$total_books_query = "SELECT COUNT(*) as total FROM books";
$total_books_result = mysqli_query($conn, $total_books_query);
$total_books_row = mysqli_fetch_assoc($total_books_result);
$total_books = $total_books_row['total'];

$total_members_query = "SELECT COUNT(*) as total FROM users WHERE role='member'";
$total_members_result = mysqli_query($conn, $total_members_query);
$total_members_row = mysqli_fetch_assoc($total_members_result);
$total_members = $total_members_row['total'];

$total_borrowings_query = "SELECT COUNT(*) as total FROM borrowings";
$total_borrowings_result = mysqli_query($conn, $total_borrowings_query);
$total_borrowings_row = mysqli_fetch_assoc($total_borrowings_result);
$total_borrowings = $total_borrowings_row['total'];

// Get featured books (most borrowed)
$featured_books_query = "SELECT b.id, b.title, b.author, b.quantity, c.name as category,
                         COUNT(br.id) as borrow_count
                         FROM books b
                         LEFT JOIN categories c ON b.category_id = c.id
                         LEFT JOIN borrowings br ON b.id = br.book_id
                         GROUP BY b.id
                         ORDER BY borrow_count DESC
                         LIMIT 6";
$featured_books_result = mysqli_query($conn, $featured_books_query);

// Get latest books
$latest_books_query = "SELECT b.id, b.title, b.author, b.quantity, c.name as category, b.created_at
                       FROM books b
                       LEFT JOIN categories c ON b.category_id = c.id
                       ORDER BY b.created_at DESC
                       LIMIT 4";
$latest_books_result = mysqli_query($conn, $latest_books_query);

// Get categories with book counts
$categories_query = "SELECT c.id, c.name, COUNT(b.id) as book_count
                     FROM categories c
                     LEFT JOIN books b ON c.id = b.category_id
                     GROUP BY c.id
                     ORDER BY book_count DESC
                     LIMIT 6";
$categories_result = mysqli_query($conn, $categories_query);

// Prepare data for template
$page_data = [
    'total_books' => $total_books,
    'total_members' => $total_members,
    'total_borrowings' => $total_borrowings,
    'featured_books' => $featured_books_result,
    'categories' => $categories_result,
    'is_logged_in' => isset($_SESSION['role']),
    'user_role' => $_SESSION['role'] ?? null
];

// Include the HTML template
include 'homepage_content.php';
?>
