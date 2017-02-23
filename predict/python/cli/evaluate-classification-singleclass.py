import sys
import json

# Sklearn binary classifier - logistic regression.
from BinaryClassifier import BinaryClassifier

# TensorFlow binary classifier - NN.
from BinaryClassifierTensorFlow import BinaryClassifierTensorFlow

# TensorFlow binary classifier - logistic regression.
from BinaryClassifierSkflow import BinaryClassifierSkflow

# TensorFlow binary classifier - deep neural network.
from BinaryClassifierDNN import BinaryClassifierDNN

# Missing arguments.
if len(sys.argv) < 7:
    result = dict()
    result['status'] = Classifier.GENERAL_ERROR
    result['errors'] = ['Missing arguments, you should set:\
- The model unique identifier\
- The directory to store all generated outputs\
- The training file\
- The minimum score (from 0 to 1) to consider the model as valid (defaults to 0.7)\
- The minimum deviation to accept the model as valid (defaults to 0.02)\
- The number of times the evaluation will run (defaults to 100)\
Received: ' + ' '.join(sys.argv)]

    # Add the provided unique id.
    if len(sys.argv) > 1:
        result['modelid'] = sys.argv[1]

    print(json.dumps(result))
    sys.exit(result['status'])

modelid = sys.argv[1]
directory = sys.argv[2]

# Sklearn binary classifier - logistic regression.
#binary_classifier = BinaryClassifier(modelid, directory)
# TensorFlow binary classifier - NN.
#binary_classifier = BinaryClassifierTensorFlow(modelid, directory)
# TensorFlow binary classifier - logistic regression.
#binary_classifier = BinaryClassifierSkflow(modelid, directory)
# TensorFlow binary classifier - deep neural network.
binary_classifier = BinaryClassifierDNN(modelid, directory)

result = binary_classifier.evaluate_dataset(sys.argv[3], float(sys.argv[4]), float(sys.argv[5]), int(sys.argv[6]))

print(json.dumps(result))
sys.exit(result['status'])
