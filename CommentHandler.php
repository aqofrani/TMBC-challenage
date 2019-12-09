<?php
/**
 * Instructions:
 *
 * The following is a poorly written comment handler. Your task will be to refactor
 * the code to improve its structure, performance, and security with respect to the
 * below requirements. Make any changes you feel necessary to improve the code or
 * data structure. The code doesn't need to be runnable we just want to see your
 * structure, style and approach.
 *
 * If you are unfamiliar with PHP, it is acceptable to recreate this functionality
 * in the language of your choice. However, PHP is preferred.
 *
 * Comment Handler Requirements
 * - Users can write a comment
 * - Users can write a reply to a comment
 * - Users can reply to a reply (Maximum of 2 levels in nested comments)
 * - Comments (within in the same level) should be ordered by post date
 * - Address any data security vulnerabilities
 *
 * Data Structure:
 * comments_table
 * -id
 * -parent_id (0 - designates top level comment)
 * -name
 * -comment
 * -create_date
 *
 */

/**
 * Improvements
 *
 * Data structure:
 * As we know, each comment should be related to a post. If we want to allow
 * all users able to leave a comment without registration, I suggest changing
 * the data structure to below:
 *
 * comments_table
 * -id
 * -post_id
 * -parent_id
 * -name
 * -email
 * -comment
 * -is_approved
 * -create_date
 *
 * I added three new fields "post_id", "email" and "is_approved". Now we can
 * query our comment based on the post, approval status, or for the specific
 * users.
 *
 * Security:
 * There are some security issues, which I mentioned some of them below:
 *
 * -"Mysql_fetch_assoc" and "mysql_query" are deprecated from PHP 7.0, so we should
 * use "PDO" instated of "mysql" to make connection to database.
 *
 * -The code allows for SQL Injection: The code accepts user input and includes it
 * directly in the SQL statement.This allows an attacker to inject SQL into the query,
 * therefore tricking the application into sending a malformed query to the database.
 * When dealing with SQL queries that contain user input, use prepared statements(we
 * should use PDO)
 *
 * -Input Validation: The code does not validate function and user's inputs.for example
 * in "addComment" we should check "$comment" array to make sure all needed parameters
 * for insert has been passed to function.
 * Also, we need to check the type of variables too.
 *
 * -Error handling:
 * The code does not handle errors, and all errors are displaying to the user. If we are
 * going to use this class in other classes in the project may be, it's better to use a
 * try-catch block to manage errors.
 *
 * -Errors are not logged:
 * If you don’t keep a log of database errors, you miss the opportunity to gather information.
 * This information could help you improve the security of your application.You can log errors
 * to the PHP error log or to another file of your choice.
 *
 * Performance:
 * Some changes help us to improve our performance. I mentioned some of them below:
 *
 * -Add constructor function:
 * In the code, we connect to the database for each function separately. It is better to use a
 * variable which will define in the constructor function to connect to the database. In this way,
 * we can maintain or code easily.
 *
 * -Reusable code:
 * In the "getComments" function, we are using the same code to get comments and replies.
 * I propose to have a method to handle this functionality. It's cleaner and easier to maintain.
 * Also, it reduces complexity from O(n3) to O(n).
 *
 */


Class CommentHandler {

    private $host = "localhost";
    private $user = "root";
    private $password = "root";
    private $database = "test";
    private $db;

    /**
     * CommentHandler constructor.
     *
     * Initiate variables
     *
     */
    public function __construct()
    {
        try{
            $this->db = $this->createConnection();
        }catch (Exception $e){
            error_log("Failed to connect to database!", 0);
            throw $e;
        }
    }

    /**
     * Create PDO object to connect to database.
     * @return PDO object
     */
    private function createConnection(){
        return new PDO("mysql:host=$this->host;dbname=$this->database",$this->user,$this->password);
    }

    /**
     * This function returns an array based on given parameters.
     * The array contains all comments and replies.
     * If the comment does not have any replies, the function returns an empty
     * array for replies index.
     * @param null $post_id
     * @param null $email
     * @param null $is_approved
     * @return array
     * @throws Exception
     */
    public function getComments($post_id=null,$email=null,$is_approved=null){
        $query = "Select * From comments_table Where parent_id=0 ";
        if($post_id){
            $query .= sprintf(" And post_id = %s",$post_id);
        }
        if($email){
            $query .= sprintf(" And email = '%s'",$email);
        }
        if($is_approved !== null){
            $query .= sprintf(" And is_approved = %s",$is_approved);
        }
        $query .= "Order By create_date Desc";
        try {
            $pdo = $this->db->prepare($query);
            $pdo->execute();
            $comments = $pdo->fetchAll(PDO::FETCH_ASSOC);
            foreach ($comments as $key => $comment){
                $comments[$key]['replies'] = $this->getReplies($comment['id']);
            }
            return $comments;
        } catch (Exception $e) {
            error_log("Failed to get comments!", 0);
            throw $e;
        }
    }

    /**
     * This is a recursive function that returns the comment's replies.
     * @param $comment_id
     * @return array
     * @throws Exception
     */
    public function getReplies($comment_id){
        try{
            $query = "Select * From comments_table Where parent_id=$comment_id  Order By create_date";
            $pdo = $this->db->prepare($query);
            $pdo->execute();
            $replies = $pdo->fetchAll(PDO::FETCH_ASSOC);
            if( count($replies)==0){
                return [];
            }
            foreach ($replies as $key => $reply){
                $replies[$key]['replies'] = $this->getReplies($reply['id']);
            }
            return $replies;
        }catch (Exception $e){
            error_log("Failed to get replies!", 0);
            throw $e;
        }
    }


    /**
     * This function accepts "post_id" and comment's data to insert a comment
     * or reply in the database. Before insert data, it validates data and throws
     * the right exception if it faces any error.
     * @param $post_id
     * @param $comment
     * @return mixed
     * @throws Exception
     */
    public function addComment($post_id,$comment){

        if(!$post_id){
            $message = "The post_id must be provide!";
            error_log($message, 0);
            throw new Exception($message);
        }

        if( !array_key_exists('parent_id',$comment) ||
            !array_key_exists('name',$comment) ||
            !array_key_exists('email',$comment) ||
            !array_key_exists('comment',$comment) ){
            $message = "The comment's information must be provide!";
            error_log($message, 0);
            throw new Exception($message);
        }

        if( !($comment['parent_id']) ||
            !($comment['name']) ||
            !($comment['email']) ||
            !($comment['comment']) ){
            $message = "The comment's information must be provide!";
            error_log($message, 0);
            throw new Exception($message);
        }

        if($comment['parent_id'] != 0 && !$this->canReplyOnComment($comment['parent_id'])){
            $message = "The nested comment can not be more that to level!";
            error_log($message, 0);
            throw new Exception($message);
        }

        try{

            $insert = $this->db->prepare("Insert into comments_table (post_id, parent_id, name, email, comment, is_approved, create_date)
                                                VALUES (:post_id, :parent_id, :name, :email, :comment, :is_approved, :create_date)");
            $result = $insert->execute([
                'post_id' => $post_id,
                'parent_id' => $comment['parent_id'],
                'name' => $comment['name'],
                'email' => $comment['email'],
                'comment' => $comment['comment'],
                'is_approved' => $comment['is_approved'],
                'create_date' => date("Y-m-d H:i:s")
            ]);

            if(!$result){
                $message = $insert->errorInfo()[2];
                error_log($message, 0);
                throw new Exception($message);
            }
            $id = $this->db->lastInsertId();
            $pdo = $this->db->prepare("Select * From comments_table Where id = $id");
            $pdo->execute();
            return $pdo->fetch(PDO::FETCH_ASSOC);

        }catch (Exception $e){
            $message = "The error happened when trying to add a new comment!";
            error_log($message, 0);
            throw new Exception($message);
        }
    }

    /**
     * This function validates the comment and returns a boolean value, which
     * shows that the comment is insertable as a reply or not based on the level
     * of the comment.
     * @param $comment_id
     * @return bool
     * @throws Exception
     */
    private function canReplyOnComment($comment_id){
        $pdo = $this->db->prepare("Select EXISTS(Select id From comments_table Where id = $comment_id)");
        $pdo->execute();
        if(!$pdo->fetchColumn()){
            $message = "The comment with id $comment_id not found!";
            error_log($message, 0);
            throw new Exception($message);
        }
        $query  = "Select	parent_id ";
        $query .= "From 	comments_table ";
        $query .= "Where	id	=	(	Select	parent_id ";
        $query .= "					From 	comments_table ";
        $query .= "					Where	id	=	(	Select	parent_id ";
        $query .= "										From 	comments_table ";
        $query .= "										Where	id	=	$comment_id))";
        $pdo = $this->db->prepare($query);
        $pdo->execute();
        $parent_id = $pdo->fetchColumn();
        if ($parent_id == 0){
            return true;
        }
        return false;
    }

}
