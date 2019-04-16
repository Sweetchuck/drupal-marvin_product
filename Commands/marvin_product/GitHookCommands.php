<?php

namespace Drush\Commands\marvin_product;

use Drush\Commands\marvin\GitHookCommandsBase;
use Robo\Collection\CollectionBuilder;

class GitHookCommands extends GitHookCommandsBase {

  /**
   * Git hook callback command for "./.git/hooks/applypatch-msg".
   *
   * @command marvin:git-hook:applypatch-msg
   * @bootstrap max
   * @hidden
   */
  public function gitHookApplyPatchMsg(string $commitMsgFileName): CollectionBuilder {
    return $this->delegate('applypatch-msg', $commitMsgFileName);
  }

  /**
   * Git hook callback command for "./.git/hooks/commit-msg".
   *
   * @command marvin:git-hook:commit-msg
   * @bootstrap max
   * @hidden
   */
  public function gitHookCommitMsg(string $commitMsgFileName): CollectionBuilder {
    return $this->delegate('commit-msg', $commitMsgFileName);
  }

  /**
   * Git hook callback command for "./.git/hooks/post-applypatch".
   *
   * @command marvin:git-hook:post-applypatch
   * @bootstrap max
   * @hidden
   */
  public function gitHookPostApplyPatch(): CollectionBuilder {
    return $this->delegate('post-applypatch');
  }

  /**
   * Git hook callback command for "./.git/hooks/post-checkout".
   *
   * @command marvin:git-hook:post-checkout
   * @bootstrap max
   * @hidden
   *
   * @todo Consider to change the @bootstrap to "none", because after `git
   *       checkout` the code base can be inconsistent.
   *       (3rd-party packages aren't up to date; composer.lock).
   *       This can be true for other Git hooks as well.
   */
  public function gitHookPostCheckout(string $refPrevious, string $refHead, bool $isBranchCheckout): CollectionBuilder {
    return $this->delegate('post-checkout', $refPrevious, $refHead, $isBranchCheckout);
  }

  /**
   * Git hook callback command for "./.git/hooks/post-commit".
   *
   * @command marvin:git-hook:post-commit
   * @bootstrap max
   * @hidden
   */
  public function gitHookPostCommit(): CollectionBuilder {
    return $this->delegate('post-commit');
  }

  /**
   * Git hook callback command for "./.git/hooks/post-merge".
   *
   * @command marvin:git-hook:post-merge
   * @bootstrap max
   * @hidden
   */
  public function gitHookPostMerge(bool $isSquashMerge): CollectionBuilder {
    return $this->delegate('post-merge', $isSquashMerge);
  }

  /**
   * Git hook callback command for "./.git/hooks/post-receive".
   *
   * @command marvin:git-hook:post-receive
   * @bootstrap max
   * @hidden
   */
  public function gitHookPostReceive(): CollectionBuilder {
    return $this->delegate('post-receive');
  }

  /**
   * Git hook callback command for "./.git/hooks/post-rewrite".
   *
   * @command marvin:git-hook:post-rewrite
   * @bootstrap max
   * @hidden
   */
  public function gitHookPostRewrite(string $commandType): CollectionBuilder {
    return $this->delegate('post-rewrite', $commandType);
  }

  /**
   * Git hook callback command for "./.git/hooks/post-update".
   *
   * @command marvin:git-hook:post-update
   * @bootstrap max
   * @hidden
   */
  public function gitHookPostUpdate(array $refNames): CollectionBuilder {
    return $this->delegate('post-update', $refNames);
  }

  /**
   * Git hook callback command for "./.git/hooks/apply-patch".
   *
   * @command marvin:git-hook:pre-applypatch
   * @bootstrap max
   * @hidden
   */
  public function gitHookPreApplyPatch(): CollectionBuilder {
    return $this->delegate('pre-applypatch');
  }

  /**
   * Git hook callback command for "./.git/hooks/pre-auto-gc".
   *
   * @command marvin:git-hook:pre-auto-gc
   * @bootstrap max
   * @hidden
   */
  public function gitHookPreAutoGc(): CollectionBuilder {
    return $this->delegate('pre-auto-gc');
  }

  /**
   * Git hook callback command for "./.git/hooks/pre-commit".
   *
   * @command marvin:git-hook:pre-commit
   * @bootstrap max
   * @hidden
   */
  public function gitHookPreCommit(): CollectionBuilder {
    return $this->delegate('pre-commit');
  }

  /**
   * Git hook callback command for "./.git/hooks/pre-push".
   *
   * @command marvin:git-hook:pre-push
   * @bootstrap max
   * @hidden
   */
  public function gitHookPrePush(string $remoteName, string $remoteUrl): CollectionBuilder {
    return $this->delegate('pre-push', $remoteName, $remoteUrl);
  }

  /**
   * Git hook callback command for "./.git/hooks/pre-rebase".
   *
   * @command marvin:git-hook:pre-rebase
   * @bootstrap max
   * @hidden
   */
  public function gitHookPreRebase(string $upstream, ?string $branch = NULL): CollectionBuilder {
    return $this->delegate('pre-rebase', $upstream, $branch);
  }

  /**
   * Git hook callback command for "./.git/hooks/pre-receive".
   *
   * @command marvin:git-hook:pre-receive
   * @bootstrap max
   * @hidden
   */
  public function gitHookPreReceive(): CollectionBuilder {
    return $this->delegate('pre-receive');
  }

  /**
   * Git hook callback command for "./.git/hooks/prepare-commit-msg".
   *
   * @command marvin:git-hook:prepare-commit-msg
   * @bootstrap max
   * @hidden
   */
  public function gitHookPrepareCommitMsg(string $commitMsgFileName, string $messageSource = '', string $sha1 = ''): CollectionBuilder {
    return $this->delegate('prepare-commit-msg', $commitMsgFileName, $messageSource, $sha1);
  }

  /**
   * Git hook callback command for "./.git/hooks/push-to-checkout".
   *
   * @command marvin:git-hook:push-to-checkout
   * @bootstrap max
   * @hidden
   */
  public function gitHookPushToCheckout(string $newCommit): CollectionBuilder {
    return $this->delegate('push-to-checkout', $newCommit);
  }

  /**
   * Git hook callback command for "./.git/hooks/update".
   *
   * @command marvin:git-hook:update
   * @bootstrap max
   * @hidden
   */
  public function gitHookUpdate(string $refName, string $oldObjectName, string $newObjectName): CollectionBuilder {
    return $this->delegate('update', $refName, $oldObjectName, $newObjectName);
  }

}
