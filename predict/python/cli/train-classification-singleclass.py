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
if len(sys.argv) < 4:
    result = dict()
    result['runid'] = int(binary_classifier.get_runid())
    result['status'] = Classifier.GENERAL_ERROR
    result['errors'] = ['Missing arguments, you should set:\
- The model unique identifier\
- The directory to store all generated outputs\
- The training file\
Received: ' + ' '.join(sys.argv)]

    # Add the provided unique id.
    if len(sys.argv) > 1:
        result['modelid'] = sys.argv[1]

    print(json.dumps(result))
    sys.exit(result['status'])

modelid = sys.argv[1]
directory = sys.argv[2]

# Sklearn binary classifier - logistic regression.
binary_classifier = BinaryClassifier(modelid, directory)
# TensorFlow binary classifier - NN.
#binary_classifier = BinaryClassifierTensorFlow(modelid, directory)
# TensorFlow binary classifier - logistic regression.
#binary_classifier = BinaryClassifierSkflow(modelid, directory)
# TensorFlow binary classifier - deep neural network.
#binary_classifier = BinaryClassifierDNN(modelid, directory)

result = binary_classifier.train_dataset(sys.argv[3])

print(json.dumps(result))
sys.exit(result['status'])
