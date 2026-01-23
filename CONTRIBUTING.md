# Contributing to Laravel Uniformed AI

Thank you for considering contributing to the Uniformed AI package! This guide outlines the workflow for contributing features, bug fixes, and the process for releasing new versions.

## Development Workflow

### 1. Branching Strategy
We use a standard feature-branch workflow.
- **Main Branch**: `main` contains the latest stable code.
- **Feature Branches**: Create a new branch for each feature or bug fix.
  - Prefix features with `feature/` (e.g., `feature/add-anthropic-support`).
  - Prefix bug fixes with `fix/` (e.g., `fix/usage-calculation-error`).

### 2. Making Changes
1.  Fork the repository and clone it locally.
2.  Create your feature branch:
    ```bash
    git checkout -b feature/my-new-feature
    ```
3.  Implement your changes.
    - Ensure you follow the existing coding style (PSR-12).
    - Add or update tests in the `tests/` directory to cover your changes.
4.  Run local verification commands:
    ```bash
    # Run tests
    ./vendor/bin/pest

    # Run static analysis
    ./vendor/bin/phpstan analyse
    ```

### 3. Pull Requests
1.  Push your branch to your fork.
2.  Open a Pull Request (PR) against the `main` branch.
3.  Provide a clear description of the problem you are solving and your proposed solution.
4.  Ensure all CI checks pass.

## Release Process (Maintainers Only)

We follow [Semantic Versioning](https://semver.org/).

### Tagging a Release
1.  Ensure the `main` branch is up to date and all tests pass.
2.  Determine the next version number based on changes (Major.Minor.Patch).
3.  Create an annotated git tag:
    ```bash
    git tag -a 1.0.0 -m "Release 1.0.0"
    ```

### Publishing
Push the tag to the repository:
```bash
git push origin 1.0.0
```
This will trigger any configured release workflows.
