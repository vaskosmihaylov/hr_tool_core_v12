<?php

namespace App\Services\Approvement;

use viki\Service\Models\Elequent\Approvement;
use viki\Service\Models\Elequent\Comment;
use viki\Service\Traits\ApprovalTrait;

class ApprovementActionService
{
    use ApprovalTrait;

    public function approve(Approvement $approvement): void
    {
        $this->approvementApprove($approvement);
    }

    public function disapprove(Approvement $approvement): void
    {
        $this->approvementDisapprove($approvement);
    }

    public function addComment(Approvement $approvement, string $comment): Comment
    {
        return Comment::create($comment, $approvement->id);
    }
}
