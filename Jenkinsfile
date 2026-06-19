pipeline {
    agent any

    environment {
        WINDOWS_IIS_ROOT = 'C:\\inetpub\\wwwroot\\anonymous-feedback'
    }

    options {
        timestamps()
        disableConcurrentBuilds()
    }

    parameters {
        booleanParam(name: 'RUN_DEPLOY', defaultValue: true, description: 'Copy apps to deployment paths after successful checks')
        choice(name: 'DEPLOY_TARGET', choices: ['both', 'internal', 'external', 'none'], description: 'Which app(s) to deploy')
        string(name: 'INTERNAL_DEPLOY_PATH', defaultValue: '', description: 'Target path for internal app deployment (Windows default: C:\\inetpub\\wwwroot\\anonymous-feedback\\internal)')
        string(name: 'EXTERNAL_DEPLOY_PATH', defaultValue: '', description: 'Target path for external app deployment (Windows default: C:\\inetpub\\wwwroot\\anonymous-feedback\\external)')
        string(name: 'SMOKE_BASE_URL', defaultValue: '', description: 'Optional base URL for HTTP smoke tests (e.g. http://dev-k2five-01:8083)')
    }

    stages {
        stage('Deployment Plan') {
            steps {
                script {
                    if (isUnix()) {
                        def internalPath = params.INTERNAL_DEPLOY_PATH?.trim() ?: '(required on Unix)'
                        def externalPath = params.EXTERNAL_DEPLOY_PATH?.trim() ?: '(required on Unix)'
                        echo "RUN_DEPLOY=${params.RUN_DEPLOY}, DEPLOY_TARGET=${params.DEPLOY_TARGET}, INTERNAL_DEPLOY_PATH=${internalPath}, EXTERNAL_DEPLOY_PATH=${externalPath}"
                    } else {
                        def internalPath = params.INTERNAL_DEPLOY_PATH?.trim() ?: "${env.WINDOWS_IIS_ROOT}\\internal"
                        def externalPath = params.EXTERNAL_DEPLOY_PATH?.trim() ?: "${env.WINDOWS_IIS_ROOT}\\external"
                        echo "RUN_DEPLOY=${params.RUN_DEPLOY}, DEPLOY_TARGET=${params.DEPLOY_TARGET}, INTERNAL_DEPLOY_PATH=${internalPath}, EXTERNAL_DEPLOY_PATH=${externalPath}"
                    }
                }
            }
        }

        stage('Tooling') {
            steps {
                script {
                    if (isUnix()) {
                        sh 'php -v'
                    } else {
                        bat 'php -v'
                    }
                }
            }
        }

        stage('Lint PHP') {
            steps {
                script {
                    if (isUnix()) {
                        sh '''
                            set -e
                            find internal external -type f -name "*.php" -print0 | xargs -0 -n1 php -l
                        '''
                    } else {
                        powershell '''
                            $ErrorActionPreference = "Stop"
                            $files = Get-ChildItem -Path internal, external -Recurse -File -Filter *.php
                            foreach ($file in $files) {
                                php -l $file.FullName
                                if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
                            }
                        '''
                    }
                }
            }
        }

        stage('Validate SQL Artifacts') {
            steps {
                script {
                    if (isUnix()) {
                        sh '''
                            set -e
                            test -f internal/database/schema.sql
                            test -f internal/database/users.sql
                            test -f external/database/schema.sql
                            test -f external/database/users.sql
                        '''
                    } else {
                        powershell '''
                            $ErrorActionPreference = "Stop"
                            $required = @(
                                "internal/database/schema.sql",
                                "internal/database/users.sql",
                                "external/database/schema.sql",
                                "external/database/users.sql"
                            )
                            foreach ($path in $required) {
                                if (-not (Test-Path $path)) {
                                    throw "Missing required file: $path"
                                }
                            }
                        '''
                    }
                }
            }
        }

        stage('Verify Internal Vendor Assets') {
            steps {
                script {
                    if (isUnix()) {
                        sh 'sh scripts/verify_internal_vendor_assets.sh'
                    } else {
                        powershell '''
                            $ErrorActionPreference = "Stop"
                            & .\scripts\verify_internal_vendor_assets.ps1
                        '''
                    }
                }
            }
        }

        stage('Deploy Internal') {
            when {
                expression {
                    return params.RUN_DEPLOY && (params.DEPLOY_TARGET == 'internal' || params.DEPLOY_TARGET == 'both')
                }
            }
            steps {
                script {
                    def internalDeployPath = params.INTERNAL_DEPLOY_PATH?.trim()
                    if (!internalDeployPath && !isUnix()) {
                        internalDeployPath = "${env.WINDOWS_IIS_ROOT}\\internal"
                    }
                    if (!internalDeployPath) {
                        error 'INTERNAL_DEPLOY_PATH is required when deploying internal app on non-Windows agents.'
                    }

                    if (isUnix()) {
                        sh '''
                            set -e
                            mkdir -p "${internalDeployPath}"
                            rsync -a \
                                --exclude '.git/' \
                                --exclude '.github/' \
                                --exclude 'uploads/' \
                                --exclude 'data/' \
                                internal/ "${internalDeployPath}/"
                        '''
                    } else {
                        env.INTERNAL_DEPLOY_PATH = internalDeployPath
                        powershell '''
                            $ErrorActionPreference = "Stop"
                            New-Item -ItemType Directory -Path $env:INTERNAL_DEPLOY_PATH -Force | Out-Null
                            robocopy internal $env:INTERNAL_DEPLOY_PATH /E /R:2 /W:2 /NFL /NDL /NJH /NJS /XD ".git" ".github" "uploads" "data"
                            $rc = $LASTEXITCODE
                            if ($rc -gt 7) { throw "robocopy failed with exit code $rc" }
                            exit 0
                        '''
                    }
                }
            }
        }

        stage('Deploy External') {
            when {
                expression {
                    return params.RUN_DEPLOY && (params.DEPLOY_TARGET == 'external' || params.DEPLOY_TARGET == 'both')
                }
            }
            steps {
                script {
                    def externalDeployPath = params.EXTERNAL_DEPLOY_PATH?.trim()
                    if (!externalDeployPath && !isUnix()) {
                        externalDeployPath = "${env.WINDOWS_IIS_ROOT}\\external"
                    }
                    if (!externalDeployPath) {
                        error 'EXTERNAL_DEPLOY_PATH is required when deploying external app on non-Windows agents.'
                    }

                    if (isUnix()) {
                        sh '''
                            set -e
                            mkdir -p "${externalDeployPath}"
                            rsync -a \
                                --exclude '.git/' \
                                --exclude '.github/' \
                                --exclude 'uploads/' \
                                --exclude 'data/' \
                                external/ "${externalDeployPath}/"
                        '''
                    } else {
                        env.EXTERNAL_DEPLOY_PATH = externalDeployPath
                        powershell '''
                            $ErrorActionPreference = "Stop"
                            New-Item -ItemType Directory -Path $env:EXTERNAL_DEPLOY_PATH -Force | Out-Null
                            robocopy external $env:EXTERNAL_DEPLOY_PATH /E /R:2 /W:2 /NFL /NDL /NJH /NJS /XD ".git" ".github" "uploads" "data"
                            $rc = $LASTEXITCODE
                            if ($rc -gt 7) { throw "robocopy failed with exit code $rc" }
                            exit 0
                        '''
                    }
                }
            }
        }

        stage('Verify Deployment') {
            when {
                expression {
                    return params.RUN_DEPLOY && params.DEPLOY_TARGET != 'none'
                }
            }
            steps {
                script {
                    def verifyInternal = (params.DEPLOY_TARGET == 'internal' || params.DEPLOY_TARGET == 'both')
                    def verifyExternal = (params.DEPLOY_TARGET == 'external' || params.DEPLOY_TARGET == 'both')

                    def internalDeployPath = params.INTERNAL_DEPLOY_PATH?.trim()
                    def externalDeployPath = params.EXTERNAL_DEPLOY_PATH?.trim()

                    if (!isUnix()) {
                        if (!internalDeployPath) {
                            internalDeployPath = "${env.WINDOWS_IIS_ROOT}\\internal"
                        }
                        if (!externalDeployPath) {
                            externalDeployPath = "${env.WINDOWS_IIS_ROOT}\\external"
                        }
                    }

                    if (isUnix()) {
                        if (verifyInternal) {
                            sh """
                                set -e
                                test -f '${internalDeployPath}/index.php'
                                test -f '${internalDeployPath}/app/bootstrap.php'
                            """
                        }
                        if (verifyExternal) {
                            sh """
                                set -e
                                test -f '${externalDeployPath}/index.php'
                                test -f '${externalDeployPath}/app/bootstrap.php'
                            """
                        }
                    } else {
                        env.VERIFY_INTERNAL_PATH = internalDeployPath ?: ''
                        env.VERIFY_EXTERNAL_PATH = externalDeployPath ?: ''
                        env.VERIFY_INTERNAL_ENABLED = verifyInternal ? '1' : '0'
                        env.VERIFY_EXTERNAL_ENABLED = verifyExternal ? '1' : '0'

                        powershell '''
                            $ErrorActionPreference = "Stop"

                            function Assert-FileExists {
                                param([string]$Path)
                                if (-not (Test-Path -Path $Path -PathType Leaf)) {
                                    throw "Deployment verification failed. Missing file: $Path"
                                }
                            }

                            if ($env:VERIFY_INTERNAL_ENABLED -eq '1') {
                                Assert-FileExists (Join-Path $env:VERIFY_INTERNAL_PATH 'index.php')
                                Assert-FileExists (Join-Path $env:VERIFY_INTERNAL_PATH 'app\\bootstrap.php')
                            }

                            if ($env:VERIFY_EXTERNAL_ENABLED -eq '1') {
                                Assert-FileExists (Join-Path $env:VERIFY_EXTERNAL_PATH 'index.php')
                                Assert-FileExists (Join-Path $env:VERIFY_EXTERNAL_PATH 'app\\bootstrap.php')
                            }
                        '''
                    }
                }
            }
        }

        stage('Smoke Test External API') {
            when {
                expression {
                    def targetIncludesExternal = (params.DEPLOY_TARGET == 'external' || params.DEPLOY_TARGET == 'both')
                    return params.RUN_DEPLOY && targetIncludesExternal && (params.SMOKE_BASE_URL?.trim())
                }
            }
            steps {
                script {
                    def baseUrl = params.SMOKE_BASE_URL.trim().replaceAll('/+$', '')
                    if (isUnix()) {
                        sh """
                            set -e
                            curl -fsS '${baseUrl}/api/health/storage' | grep -q '"ok"'
                        """
                    } else {
                        env.SMOKE_BASE_URL = baseUrl
                        powershell '''
                            $ErrorActionPreference = "Stop"
                            $url = "$env:SMOKE_BASE_URL/api/health/storage"
                            $resp = Invoke-RestMethod -Method Get -Uri $url -TimeoutSec 30
                            if ($null -eq $resp -or $null -eq $resp.data) {
                                throw "Smoke test failed. No JSON data from $url"
                            }
                            if (-not $resp.data.ok) {
                                throw "Smoke test failed. Storage health not OK at $url"
                            }
                        '''
                    }
                }
            }
        }

        stage('Smoke Test Internal API') {
            when {
                expression {
                    def targetIncludesInternal = (params.DEPLOY_TARGET == 'internal' || params.DEPLOY_TARGET == 'both')
                    return params.RUN_DEPLOY && targetIncludesInternal && (params.SMOKE_BASE_URL?.trim())
                }
            }
            steps {
                script {
                    def baseUrl = params.SMOKE_BASE_URL.trim().replaceAll('/+$', '')
                    if (isUnix()) {
                        sh """
                            set -e
                            curl -fsS '${baseUrl}/api/categories' | grep -q '"data"'
                        """
                    } else {
                        env.SMOKE_BASE_URL = baseUrl
                        powershell '''
                            $ErrorActionPreference = "Stop"
                            $url = "$env:SMOKE_BASE_URL/api/categories"
                            $resp = Invoke-RestMethod -Method Get -Uri $url -TimeoutSec 30
                            if ($null -eq $resp -or $null -eq $resp.data) {
                                throw "Smoke test failed. No JSON category data from $url"
                            }
                        '''
                    }
                }
            }
        }
    }

    post {
        success {
            echo 'Pipeline completed successfully.'
        }
        failure {
            echo 'Pipeline failed. Check stage logs for details.'
        }
    }
}
